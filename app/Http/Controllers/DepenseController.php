<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UnlockDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Support\ModuleContext;
use App\Models\Depense;
use App\Models\DepenseHistory;
use App\Models\Gare;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\CashierVirtualGareService;
use App\Services\FinancialUnlockNotificationService;
use App\Support\UploadedFileName;
use App\Support\UploadedImageNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepenseController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected ActivityLogService $activity,
        protected CashierVirtualGareService $virtualGares,
        protected FinancialUnlockNotificationService $unlockNotifications
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Depense::class);
        $user = $request->user();

        $query = Depense::query()
            ->with(['gare', 'creator', 'unlockedBy', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows() && $user->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);

        $this->access->scopeForUser($query, $user, 'gare_id', $serviceScope);

        $query->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', $request->integer('gare_id')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')))
            ->when($request->filled('creator_name'), function ($q) use ($request) {
                $term = trim($request->string('creator_name')->toString());
                $q->whereHas('creator', fn ($creatorQuery) => $creatorQuery->where('name', 'like', "%{$term}%"));
            })
            ->when($request->filled('creator_phone'), function ($q) use ($request) {
                $phone = trim($request->string('creator_phone')->toString());
                $q->whereHas('creator', fn ($creatorQuery) => $creatorQuery->where('phone', 'like', "%{$phone}%"));
            })
            ->when($request->filled('modification_state'), function ($q) use ($request) {
                $state = $request->string('modification_state')->toString();
                match ($state) {
                    'unlock_active' => $q->whereNotNull('force_unlocked_until')->where('force_unlocked_until', '>', now()),
                    'unlock_expired' => $q->whereNotNull('force_unlocked_until')->where('force_unlocked_until', '<=', now()),
                    'locked' => $q->whereNull('force_unlocked_until'),
                    default => null,
                };
            });

        if (! $request->filled('start_date') && ! $request->filled('end_date')) {
            $query->whereDate('operation_date', now('Africa/Abidjan')->toDateString());
        }

        return view('depenses.index', [
            'depenses' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user, $serviceScope),
            'module' => $module,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Depense::class);

                $module = ModuleContext::fromRequest($request);
        abort_unless($module->supportsFinancialFlows() && $request->user()->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);
        $virtualGare = $request->user()->canActAsCashierForScope($serviceScope)
            ? $this->virtualGares->ensureForScope($request->user(), $serviceScope)
            : null;

        return view('depenses.create', [
            'gares' => $this->access->availableGares($request->user(), $serviceScope),
            'module' => $module,
            'virtualGare' => $virtualGare,
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 10240),
            'initialEntries' => old('entries', [[
                'operation_date' => '',
                'amount' => null,
                'motif' => null,
                'reference' => null,
                'description' => null,
                'gare_id' => null,
                'justificatif_name' => null,
            ]]),
        ]);
    }

    public function store(StoreDepenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Depense::class);
        $user = $request->user();
        $entries = $request->validated('entries');
        $module = ModuleContext::fromRequest($request, $user);
        $serviceScope = ModuleContext::financialScope($module);
        $virtualGare = $user->canActAsCashierForScope($serviceScope)
            ? $this->virtualGares->ensureForScope($user, $serviceScope)
            : null;

        $createdCount = DB::transaction(function () use ($entries, $request, $user, $serviceScope, $virtualGare) {
            $count = 0;

            foreach ($entries as $index => $entry) {
                $depense = Depense::create([
                    'gare_id' => $virtualGare?->id
                        ?: $this->access->resolveGareIdForCreation($user, data_get($entry, 'gare_id'), $serviceScope),
                    'service_scope' => $serviceScope,
                    'operation_date' => data_get($entry, 'operation_date'),
                    'amount' => data_get($entry, 'amount'),
                    'motif' => data_get($entry, 'motif'),
                    'reference' => data_get($entry, 'reference'),
                    'description' => data_get($entry, 'description'),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $uploadedFiles = $this->uploadedDepenseJustificatifs($request, "entries.$index");
                if ($uploadedFiles !== []) {
                    $gareName = Gare::query()->whereKey($depense->gare_id)->value('name') ?: 'Gare';
                    foreach ($uploadedFiles as $file) {
                        $this->attachUploadedPiece(
                            $depense,
                            $file,
                            $user->id,
                            $this->defaultDepenseJustificatifName(
                                data_get($entry, 'justificatif_name'),
                                $gareName,
                                (string) data_get($entry, 'operation_date', '')
                            )
                        );
                    }
                }

                $this->activity->log($user, 'depense_created', $depense, 'Création d\'une dépense.', [
                    'gare_id' => $depense->gare_id,
                    'after' => $depense->only(['gare_id', 'operation_date', 'amount', 'motif', 'reference', 'description']),
                ]);

                $count++;
            }

            return $count;
        });

        $message = $createdCount > 1
            ? $createdCount.' dépenses enregistrées.'
            : 'Dépense enregistrée.';

        return redirect()->route('depenses.index', ['module' => $module->value])->with('status', $message);
    }

    public function edit(Depense $depense): View
    {
        $this->authorize('update', $depense);

                $module = ($depense->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        return view('depenses.edit', [
            'depense' => $depense->load(['gare', 'histories.modifier', 'justificatives']),
            'gares' => $this->access->availableGares(auth()->user(), $depense->service_scope ?? 'gares'),
            'module' => $module,
            'virtualGare' => auth()->user()->canActAsCashierForScope($depense->service_scope ?? 'gares')
                ? $this->virtualGares->ensureForScope(auth()->user(), $depense->service_scope ?? 'gares')
                : null,
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 10240),
        ]);
    }

    public function update(UpdateDepenseRequest $request, Depense $depense): RedirectResponse
    {
        $this->authorize('update', $depense);

        $before = $depense->only(['operation_date', 'amount', 'motif', 'reference', 'description', 'gare_id']);
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $scope = (string) ($depense->service_scope ?? 'gares');
        $virtualGare = $request->user()->canActAsCashierForScope($scope)
            ? $this->virtualGares->ensureForScope($request->user(), $scope)
            : null;

                $module = ($depense->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        if ($virtualGare) {
            $data['gare_id'] = $virtualGare->id;
        } elseif (! $request->user()->canActAsChefForScope((string) ($depense->service_scope ?? 'gares')) || $request->user()->canUseMultiGareEntry()) {
            $requestedGareId = $request->filled('gare_id') ? $request->integer('gare_id') : null;
            if ($requestedGareId && $requestedGareId > 0 && ! $request->user()->hasAccessToGare($requestedGareId, (string) ($depense->service_scope ?? 'gares'))) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare selectionnee est invalide pour votre profil.',
                ]);
            }
            $data['gare_id'] = $requestedGareId && $requestedGareId > 0 ? $requestedGareId : (int) $depense->gare_id;
        }

        unset($data['justificatif'], $data['justificatifs'], $data['justificatif_name']);

        $depense->update($data);

        $after = $depense->fresh()->only(['operation_date', 'amount', 'motif', 'reference', 'description', 'gare_id']);
        $hasFieldChanges = $this->hasMeaningfulChanges($before, $after);

        if ($hasFieldChanges) {
            DepenseHistory::create([
                'depense_id' => $depense->id,
                'modified_by' => $request->user()->id,
                'before' => $before,
                'after' => $after,
                'comment' => $request->string('history_comment')->toString() ?: 'Modification de dépense',
            ]);
        }

        $uploadedFiles = $this->uploadedDepenseJustificatifs($request);
        if ($uploadedFiles !== []) {
            $this->replaceJustificatifs($depense);
            $gareName = Gare::query()->whereKey($depense->gare_id)->value('name') ?: 'Gare';
            foreach ($uploadedFiles as $file) {
                $this->attachUploadedPiece(
                    $depense,
                    $file,
                    $request->user()->id,
                    $this->defaultDepenseJustificatifName(
                        $request->string('justificatif_name')->toString(),
                        $gareName,
                        (string) ($data['operation_date'] ?? $depense->operation_date?->toDateString() ?? '')
                    )
                );
            }

            $this->activity->log($request->user(), 'depense_attachment_added', $depense, 'Ajout d\'un justificatif sur une dépense.', [
                'gare_id' => $depense->gare_id,
                'after' => [
                    'pieces_total' => count($uploadedFiles),
                ],
            ]);
        }

        if ($hasFieldChanges) {
            $this->activity->log($request->user(), 'depense_updated', $depense, 'Modification d\'une dépense.', [
                'before' => $before,
                'after' => $after,
                'gare_id' => $depense->gare_id,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges || ($uploadedFiles !== []) ? 'Dépense modifiée.' : 'Aucune modification détectée sur la dépense.';

        return redirect()->route('depenses.index', ['module' => $module->value])->with('status', $status);
    }

    public function unlock(UnlockDepenseRequest $request, Depense $depense): RedirectResponse
    {
        $user = $request->user();
        $scope = (string) ($depense->service_scope ?? 'gares');

        abort_unless($user->canUnlockFinancialScope($scope), 403);
        abort_unless(
            $user->canAdministerFinancialScope($scope) || $user->hasAccessToGare((int) $depense->gare_id, $scope),
            403
        );
        $validated = $request->validated();
        $duration = (int) ($validated['unlock_duration'] ?? 0);
        $unit = (string) ($validated['unlock_unit'] ?? 'hours');
        $until = match ($unit) {
            'minutes' => now()->addMinutes($duration),
            'days' => now()->addDays($duration),
            default => now()->addHours($duration),
        };
        $unitLabel = match ($unit) {
            'minutes' => 'minute(s)',
            'days' => 'jour(s)',
            default => 'heure(s)',
        };

        $before = $depense->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $depense->update([
            'force_unlocked_until' => $until,
            'unlock_reason' => $validated['unlock_reason'],
            'unlocked_by' => $user->id,
        ]);

        $this->activity->log($user, 'depense_unlocked', $depense, 'Déverrouillage superviseur d\'une dépense.', [
            'before' => $before,
            'after' => $depense->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
            'gare_id' => $depense->gare_id,
        ]);

        $this->unlockNotifications->notifyStationManagerForUnlock(
            (string) ($depense->service_scope ?? 'gares'),
            (int) $depense->gare_id,
            'depense',
            (int) $depense->id,
            $depense->operation_date,
            $until,
            (string) ($validated['unlock_reason'] ?? ''),
            $user
        );

        return back()->with('status', "Déverrouillage actif pour {$duration} {$unitLabel}.");
    }

    protected function attachUploadedPiece(Depense $depense, UploadedFile $file, int $userId, ?string $desiredName = null)
    {
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $directory = 'justificatifs/depenses';
        $normalizedBlob = UploadedImageNormalizer::convertHeicToJpegBlob($file);

        if ($normalizedBlob !== null) {
            $storedFileName = (string) Str::uuid().'.jpg';
            $path = $directory.'/'.$storedFileName;
            Storage::disk($disk)->put($path, $normalizedBlob);
            $originalName = UploadedFileName::build($desiredName, $file, 'document.jpg');
            $mimeType = 'image/jpeg';
            $size = strlen($normalizedBlob);
        } else {
            $path = $file->store($directory, $disk);
            $originalName = UploadedFileName::build($desiredName, $file);
            $mimeType = $file->getMimeType() ?: ($file->getClientMimeType() ?: 'application/octet-stream');
            $size = $file->getSize();
        }

        return $depense->justificatives()->create([
            'document_type' => 'depense',
            'original_name' => $originalName,
            'file_name' => basename($path),
            'mime_type' => $mimeType,
            'size' => $size,
            'disk' => $disk,
            'path' => $path,
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
        ]);
    }

    protected function hasMeaningfulChanges(array $before, array $after): bool
    {
        return collect($before)->keys()->contains(function ($key) use ($before, $after) {
            return $this->normalizeHistoryValue($before[$key] ?? null) !== $this->normalizeHistoryValue($after[$key] ?? null);
        });
    }

    protected function normalizeHistoryValue(mixed $value): string
    {
        if (is_array($value)) {
            ksort($value);

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    protected function replaceJustificatifs(Depense $depense): void
    {
        foreach ($depense->justificatives()->get() as $piece) {
            if ($piece->disk && $piece->path) {
                Storage::disk($piece->disk)->delete($piece->path);
            }
            $piece->delete();
        }
    }

    protected function defaultDepenseJustificatifName(?string $desiredName, string $gareName, string $operationDate): string
    {
        $desired = trim((string) $desiredName);
        if ($desired !== '') {
            return $desired;
        }

        $gare = trim($gareName) !== '' ? trim($gareName) : 'Gare';
        $date = trim($operationDate) !== '' ? trim($operationDate) : now('Africa/Abidjan')->toDateString();

        return "Dépense {$gare} {$date}";
    }
    protected function uploadedDepenseJustificatifs(Request $request, ?string $entryPrefix = null): array
    {
        if ($entryPrefix) {
            if ($request->hasFile($entryPrefix.'.justificatifs')) {
                $files = $request->file($entryPrefix.'.justificatifs');

                return is_array($files) ? array_values(array_filter($files)) : (filled($files) ? [$files] : []);
            }

            if ($request->hasFile($entryPrefix.'.justificatif')) {
                $single = $request->file($entryPrefix.'.justificatif');

                return filled($single) ? [$single] : [];
            }

            return [];
        }

        if ($request->hasFile('justificatifs')) {
            $files = $request->file('justificatifs');

            return is_array($files) ? array_values(array_filter($files)) : (filled($files) ? [$files] : []);
        }

        if ($request->hasFile('justificatif')) {
            $single = $request->file('justificatif');

            return filled($single) ? [$single] : [];
        }

        return [];
    }
}

