<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVersementBancaireRequest;
use App\Http\Requests\UnlockVersementBancaireRequest;
use App\Http\Requests\UpdateVersementBancaireRequest;
use App\Support\ModuleContext;
use App\Models\Gare;
use App\Models\PieceJustificative;
use App\Models\VersementBancaire;
use App\Models\VersementBancaireHistory;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\BankRoutingService;
use App\Services\CashierVirtualGareService;
use App\Services\FinancialUnlockNotificationService;
use App\Support\UploadedFileName;
use App\Support\UploadedImageNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VersementBancaireController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected ActivityLogService $activity,
        protected CashierVirtualGareService $virtualGares,
        protected BankRoutingService $bankRouting,
        protected FinancialUnlockNotificationService $unlockNotifications
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VersementBancaire::class);
        $user = $request->user();

        $query = VersementBancaire::query()
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

        return view('versements.index', [
            'versements' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user, $serviceScope),
            'module' => $module,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', VersementBancaire::class);

                $module = ModuleContext::fromRequest($request);
        abort_unless($module->supportsFinancialFlows() && $request->user()->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);
        $isCashierScope = $request->user()->canActAsCashierForScope($serviceScope);
        $virtualGare = $isCashierScope
            ? $this->virtualGares->ensureForScope($request->user(), $serviceScope)
            : null;
        $gares = $isCashierScope
            ? collect([$virtualGare])
            : $this->access->availableGares($request->user(), $serviceScope)
                ->filter(fn (Gare $gare) => ($gare->versement_mode ?? 'direct') === 'direct')
                ->values();
        $defaultOperationDate = old('operation_date', now('Africa/Abidjan')->toDateString());
        $defaultGareId = (int) old('gare_id', $virtualGare?->id ?? $gares->first()?->id ?? 0);

        return view('versements.create', [
            'gares' => $gares,
            'module' => $module,
            'virtualGare' => $virtualGare,
            'bankRoutingWindows' => $this->bankRouting->activeWindowsForScope($serviceScope),
            'forcedAccountType' => $this->bankRouting->forcedAccountTypeForDate($serviceScope, $defaultOperationDate, $defaultGareId ?: null),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function store(StoreVersementBancaireRequest $request): RedirectResponse
    {
        $this->authorize('create', VersementBancaire::class);

        $user = $request->user();
        $data = $request->validated();

                $module = ModuleContext::fromRequest($request, $user);
        $serviceScope = ModuleContext::financialScope($module);
        $isCashierScope = $user->canActAsCashierForScope($serviceScope);
        $virtualGare = $isCashierScope
            ? $this->virtualGares->ensureForScope($user, $serviceScope)
            : null;

        $data['gare_id'] = $virtualGare?->id
            ?: $this->access->resolveGareIdForCreation($user, $request->integer('gare_id'), $serviceScope);
        $data['service_scope'] = $serviceScope;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        if (! $virtualGare) {
            $targetGare = Gare::query()->find($data['gare_id']);
            if ($targetGare && ($targetGare->versement_mode ?? 'direct') === 'cashier') {
                throw ValidationException::withMessages([
                    'gare_id' => 'Cette gare est rattachee a un caissier. Le versement se fait uniquement au niveau du caissier.',
                ]);
            }
        }

        $targetGare = Gare::query()->find($data['gare_id']);
        $data['account_type'] = $this->bankRouting->enforceAccountType(
            $serviceScope,
            (string) $data['operation_date'],
            (string) ($data['account_type'] ?? null),
            (int) $data['gare_id']
        );
        if ($targetGare?->isInterOnly() && ! $this->bankRouting->forcedAccountTypeForDate($serviceScope, (string) $data['operation_date'], (int) $data['gare_id'])) {
            $data['account_type'] = 'inter';
        }
        if ($targetGare?->isNationalOnly() && ! $this->bankRouting->forcedAccountTypeForDate($serviceScope, (string) $data['operation_date'], (int) $data['gare_id'])) {
            $data['account_type'] = 'national';
        }

        $data['bank_name'] = $this->normalizedBankName($data['account_type'] ?? null, $data['bank_name'] ?? null);

        $alreadyExists = VersementBancaire::query()
            ->where('service_scope', $serviceScope)
            ->where('gare_id', (int) $data['gare_id'])
            ->whereDate('operation_date', (string) $data['operation_date'])
            ->where('account_type', (string) $data['account_type'])
            ->exists();

        if ($alreadyExists) {
            $bankLabel = $data['account_type'] === 'inter' ? 'Ecobank' : 'Coris Bank';
            throw ValidationException::withMessages([
                'operation_date' => "Un versement {$bankLabel} existe deja pour cette gare a cette date.",
            ]);
        }

        unset($data['bordereau'], $data['bordereaux'], $data['bordereau_name']);

        $versement = VersementBancaire::create($data);

        $uploadedBordereaux = $this->uploadedBordereaux($request);
        if ($uploadedBordereaux !== []) {
            foreach ($uploadedBordereaux as $file) {
                $this->attachUploadedPiece(
                    $versement,
                    $file,
                    $user->id,
                    $this->defaultBordereauName(
                        $request->string('bordereau_name')->toString(),
                        (string) ($data['bank_name'] ?? ''),
                        (string) ($data['operation_date'] ?? '')
                    )
                );
            }
        }

        $this->activity->log($user, 'versement_created', $versement, 'Création d\'un versement bancaire.', [
            'gare_id' => $versement->gare_id,
            'after' => $versement->only(['gare_id', 'operation_date', 'receipt_date', 'amount', 'reference', 'bank_name', 'description']),
        ]);

        return redirect()->route('versements.index', ['module' => $module->value])->with('status', 'Versement bancaire enregistré.');
    }

    public function edit(VersementBancaire $versement): View
    {
        $this->authorize('update', $versement);

        $module = ($versement->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        $scope = $versement->service_scope ?? 'gares';
        $resolvedVirtualGare = auth()->user()->canActAsCashierForScope($scope)
            ? $this->virtualGares->ensureForScope(auth()->user(), $scope)
            : null;
        $defaultOperationDate = old('operation_date', $versement->operation_date?->toDateString() ?? now('Africa/Abidjan')->toDateString());
        $defaultGareId = (int) old('gare_id', $versement->gare_id ?? $resolvedVirtualGare?->id ?? 0);

        return view('versements.edit', [
            'versement' => $versement->load(['gare', 'histories.modifier', 'justificatives']),
            'gares' => $this->access->availableGares(auth()->user(), $scope),
            'module' => $module,
            'virtualGare' => $resolvedVirtualGare,
            'bankRoutingWindows' => $this->bankRouting->activeWindowsForScope($scope),
            'forcedAccountType' => $this->bankRouting->forcedAccountTypeForDate($scope, $defaultOperationDate, $defaultGareId ?: null),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function update(UpdateVersementBancaireRequest $request, VersementBancaire $versement): RedirectResponse
    {
        $this->authorize('update', $versement);

        $before = $versement->only([
            'operation_date',
            'receipt_date',
            'amount',
            'reference',
            'bank_name',
            'description',
            'gare_id',
        ]);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $scope = (string) ($versement->service_scope ?? 'gares');
        $isCashierScope = $request->user()->canActAsCashierForScope($scope);
        $virtualGare = $isCashierScope ? $this->virtualGares->ensureForScope($request->user(), $scope) : null;

                $module = ($versement->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        $targetGareId = $versement->gare_id;
        if ($virtualGare) {
            $targetGareId = $virtualGare->id;
            $data['gare_id'] = $virtualGare->id;
        } elseif (! $request->user()->canActAsChefForScope((string) ($versement->service_scope ?? 'gares')) || $request->user()->canUseMultiGareEntry()) {
            $requestedGareId = $request->filled('gare_id') ? $request->integer('gare_id') : null;
            if ($requestedGareId && $requestedGareId > 0 && ! $request->user()->hasAccessToGare($requestedGareId, (string) ($versement->service_scope ?? 'gares'))) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare selectionnee est invalide pour votre profil.',
                ]);
            }
            $targetGareId = $requestedGareId && $requestedGareId > 0 ? $requestedGareId : (int) $versement->gare_id;
            $data['gare_id'] = $targetGareId;
        }

        $targetGare = Gare::query()->find($targetGareId);
        if (! $virtualGare && $targetGare && ($targetGare->versement_mode ?? 'direct') === 'cashier') {
            throw ValidationException::withMessages([
                'gare_id' => 'Cette gare est rattachee a un caissier. Le versement se fait uniquement au niveau du caissier.',
            ]);
        }

        $data['account_type'] = $this->bankRouting->enforceAccountType(
            $scope,
            (string) $data['operation_date'],
            (string) ($data['account_type'] ?? null),
            (int) $targetGareId
        );
        if ($targetGare?->isInterOnly() && ! $this->bankRouting->forcedAccountTypeForDate($scope, (string) $data['operation_date'], (int) $targetGareId)) {
            $data['account_type'] = 'inter';
        }
        if ($targetGare?->isNationalOnly() && ! $this->bankRouting->forcedAccountTypeForDate($scope, (string) $data['operation_date'], (int) $targetGareId)) {
            $data['account_type'] = 'national';
        }
        $data['bank_name'] = $this->normalizedBankName($data['account_type'] ?? null, $data['bank_name'] ?? null);

        $alreadyExists = VersementBancaire::query()
            ->where('service_scope', $scope)
            ->where('gare_id', (int) $targetGareId)
            ->whereDate('operation_date', (string) $data['operation_date'])
            ->where('account_type', (string) $data['account_type'])
            ->where('id', '!=', $versement->id)
            ->exists();

        if ($alreadyExists) {
            $bankLabel = $data['account_type'] === 'inter' ? 'Ecobank' : 'Coris Bank';
            throw ValidationException::withMessages([
                'operation_date' => "Un versement {$bankLabel} existe deja pour cette gare a cette date.",
            ]);
        }

        unset($data['bordereau'], $data['bordereaux'], $data['bordereau_name']);

        $versement->update($data);

        $after = $versement->fresh()->only([
            'operation_date',
            'receipt_date',
            'amount',
            'reference',
            'bank_name',
            'description',
            'gare_id',
        ]);

        $hasFieldChanges = $this->hasMeaningfulChanges($before, $after);

        if ($hasFieldChanges) {
            VersementBancaireHistory::create([
                'versement_bancaire_id' => $versement->id,
                'modified_by' => $request->user()->id,
                'before' => $before,
                'after' => $after,
                'comment' => $request->string('history_comment')->toString() ?: 'Modification de versement',
            ]);
        }

        $uploadedBordereaux = $this->uploadedBordereaux($request);
        if ($uploadedBordereaux !== []) {
            $this->replaceJustificatifs($versement);
            foreach ($uploadedBordereaux as $file) {
                $this->attachUploadedPiece(
                    $versement,
                    $file,
                    $request->user()->id,
                    $this->defaultBordereauName(
                        $request->string('bordereau_name')->toString(),
                        (string) ($data['bank_name'] ?? $versement->bank_name ?? ''),
                        (string) ($data['operation_date'] ?? $versement->operation_date?->toDateString() ?? '')
                    )
                );
            }

            $this->activity->log($request->user(), 'versement_attachment_added', $versement, 'Ajout d\'un bordereau sur un versement bancaire.', [
                'gare_id' => $versement->gare_id,
                'after' => [
                    'pieces_total' => count($uploadedBordereaux),
                ],
            ]);
        }

        if ($hasFieldChanges) {
            $this->activity->log($request->user(), 'versement_updated', $versement, 'Modification d\'un versement bancaire.', [
                'gare_id' => $versement->gare_id,
                'before' => $before,
                'after' => $after,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges || ($uploadedBordereaux !== [])
            ? 'Versement modifié.'
            : 'Aucune modification détectée sur le versement.';

        return redirect()->route('versements.index', ['module' => $module->value])->with('status', $status);
    }

    public function unlock(UnlockVersementBancaireRequest $request, VersementBancaire $versement): RedirectResponse
    {
        abort_unless($request->user()->canUnlockFinancialScope($versement->service_scope), 403);
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

        $before = $versement->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $versement->update([
            'force_unlocked_until' => $until,
            'unlock_reason' => $validated['unlock_reason'],
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'versement_unlocked', $versement, 'Déverrouillage superviseur d\'un versement.', [
            'gare_id' => $versement->gare_id,
            'before' => $before,
            'after' => $versement->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
        ]);

        $this->unlockNotifications->notifyStationManagerForUnlock(
            (string) ($versement->service_scope ?? 'gares'),
            (int) $versement->gare_id,
            'versement_bancaire',
            (int) $versement->id,
            $versement->operation_date,
            $until,
            (string) ($validated['unlock_reason'] ?? ''),
            $request->user()
        );

        return back()->with('status', "Déverrouillage actif pour {$duration} {$unitLabel}.");
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

    protected function attachUploadedPiece(VersementBancaire $versement, UploadedFile $file, int $userId, ?string $desiredName = null): PieceJustificative
    {
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $directory = 'justificatifs/versements/'.now()->format('Y/m');
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

        return $versement->justificatives()->create([
            'document_type' => 'versement_bancaire',
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

    protected function normalizedBankName(?string $accountType, ?string $bankName): string
    {
        return match ($accountType) {
            'inter' => 'Ecobank',
            'national' => 'Coris Bank',
            default => trim((string) ($bankName ?: 'Coris Bank')),
        };
    }

    protected function replaceJustificatifs(VersementBancaire $versement): void
    {
        foreach ($versement->justificatives()->get() as $piece) {
            if ($piece->disk && $piece->path) {
                Storage::disk($piece->disk)->delete($piece->path);
            }
            $piece->delete();
        }
    }

    protected function uploadedBordereaux(Request $request): array
    {
        if ($request->hasFile('bordereaux')) {
            $files = $request->file('bordereaux');

            return is_array($files) ? array_values(array_filter($files)) : (filled($files) ? [$files] : []);
        }

        if ($request->hasFile('bordereau')) {
            $single = $request->file('bordereau');

            return filled($single) ? [$single] : [];
        }

        return [];
    }

    protected function defaultBordereauName(?string $desiredName, string $bankName, string $operationDate): string
    {
        $desired = trim((string) $desiredName);
        if ($desired !== '') {
            return $desired;
        }

        $bank = trim($bankName) !== '' ? trim($bankName) : 'Banque';
        $date = trim($operationDate) !== '' ? trim($operationDate) : now('Africa/Abidjan')->toDateString();

        return "Bordereau {$bank} {$date}";
    }
}

