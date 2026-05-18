<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecetteRequest;
use App\Http\Requests\UnlockRecetteRequest;
use App\Http\Requests\UpdateRecetteRequest;
use App\Support\ModuleContext;
use App\Models\PieceJustificative;
use App\Models\Recette;
use App\Models\RecetteHistory;
use App\Models\Gare;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\FinancialUnlockNotificationService;
use App\Support\UploadedFileName;
use App\Support\UploadedImageNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecetteController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected ActivityLogService $activity,
        protected FinancialUnlockNotificationService $unlockNotifications
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recette::class);
        $user = $request->user();

        $query = Recette::query()
            ->with(['gare', 'creator', 'updater', 'unlockedBy', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows() && $user->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);

        $this->access->scopeForUser($query, $user, 'gare_id', $serviceScope);

        if (! $user->canViewAllGares($serviceScope)) {
            $query->where(function ($inner) {
                $inner->where('created_at', '>=', now()->subHours(48))
                    ->orWhere('force_unlocked_until', '>', now());
            });
        }

        $query->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', (int) $request->integer('gare_id')))
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

        return view('recettes.index', [
            'recettes' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user, $serviceScope),
            'module' => $module,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Recette::class);

                $module = ModuleContext::fromRequest($request);
        abort_unless($module->supportsFinancialFlows() && $request->user()->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);

        return view('recettes.create', [
            'gares' => $this->access->availableGares($request->user(), $serviceScope),
            'module' => $module,
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function store(StoreRecetteRequest $request): RedirectResponse
    {
        $this->authorize('create', Recette::class);
        $user = $request->user();
        $data = $request->validated();

                $module = ModuleContext::fromRequest($request, $user);
        $serviceScope = ModuleContext::financialScope($module);

        $gareId = $this->access->resolveGareIdForCreation($user, $request->integer('gare_id'), $serviceScope);
        $operationDate = (string) data_get($data, 'operation_date');
        $duplicate = Recette::query()
            ->where('service_scope', $serviceScope)
            ->where('gare_id', $gareId)
            ->whereDate('operation_date', $operationDate)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'operation_date' => 'Une recette existe deja pour cette gare a cette date.',
            ]);
        }

        $gare = Gare::query()->find($gareId);
        $data = $this->normalizeRecetteData($data, $gare, $serviceScope);
        $data['gare_id'] = $gareId;
        $data['service_scope'] = $serviceScope;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['reference'] = null;

        $recette = Recette::create($data);

        $uploadedFiles = $this->uploadedRecetteJustificatifs($request);
        if ($uploadedFiles !== []) {
            foreach ($uploadedFiles as $file) {
                $this->attachUploadedPiece(
                    $recette,
                    $file,
                    $user->id,
                    $this->defaultRecetteJustificatifName(
                        $request->string('justificatif_name')->toString(),
                        $gare?->name ?? $recette->gare?->name ?? 'Gare',
                        (string) ($data['operation_date'] ?? '')
                    )
                );
            }
        }

        $this->activity->log($user, 'recette_created', $recette, 'Création d\'une recette.', [
            'before' => null,
            'after' => $recette->only([
                'gare_id',
                'operation_date',
                'ticket_inter_amount',
                'ticket_national_amount',
                'bagage_inter_amount',
                'bagage_national_amount',
                'amount',
                'description',
            ]),
            'gare_id' => $recette->gare_id,
        ]);

        return redirect()->route('recettes.index', ['module' => $module->value])->with('status', 'Recette enregistrée.');
    }

    public function edit(Recette $recette): View
    {
        $this->authorize('update', $recette);
        $scope = (string) ($recette->service_scope ?? 'gares');
        $user = auth()->user();
        if ($user->canActAsCashierForScope($scope) && ! $user->canActAsChefForScope($scope)) {
            abort(403, 'Le caissier valide les recettes via le menu Receptions caissier.');
        }

                $module = ($recette->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        return view('recettes.edit', [
            'recette' => $recette->load(['gare', 'histories.modifier', 'justificatives']),
            'gares' => $this->access->availableGares(auth()->user(), $recette->service_scope ?? 'gares'),
            'module' => $module,
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function update(UpdateRecetteRequest $request, Recette $recette): RedirectResponse
    {
        $this->authorize('update', $recette);
        $scope = (string) ($recette->service_scope ?? 'gares');
        if ($request->user()->canActAsCashierForScope($scope) && ! $request->user()->canActAsChefForScope($scope)) {
            abort(403, 'Le caissier valide les recettes via le menu Receptions caissier.');
        }

        $before = $recette->only([
            'operation_date',
            'ticket_inter_amount',
            'ticket_national_amount',
            'bagage_inter_amount',
            'bagage_national_amount',
            'amount',
            'description',
            'gare_id',
        ]);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $data['reference'] = null;

                $module = ($recette->service_scope ?? 'gares') === 'courrier' ? \App\Enums\ServiceModule::Courrier : \App\Enums\ServiceModule::Gares;

        $targetGareId = $recette->gare_id;
        if (! $request->user()->canActAsChefForScope((string) ($recette->service_scope ?? 'gares')) || $request->user()->canUseMultiGareEntry()) {
            $requestedGareId = $request->filled('gare_id') ? $request->integer('gare_id') : null;
            if ($requestedGareId && $requestedGareId > 0 && ! $request->user()->hasAccessToGare($requestedGareId, (string) ($recette->service_scope ?? 'gares'))) {
                throw ValidationException::withMessages([
                    'gare_id' => 'La gare selectionnee est invalide pour votre profil.',
                ]);
            }
            $targetGareId = $requestedGareId && $requestedGareId > 0 ? $requestedGareId : (int) $recette->gare_id;
            $data['gare_id'] = $targetGareId;
        }

        $targetGare = Gare::query()->find($targetGareId);
        $operationDate = (string) data_get($data, 'operation_date');
        $duplicate = Recette::query()
            ->where('service_scope', (string) ($recette->service_scope ?? 'gares'))
            ->where('gare_id', $targetGareId)
            ->whereDate('operation_date', $operationDate)
            ->where('id', '!=', $recette->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'operation_date' => 'Une recette existe deja pour cette gare a cette date.',
            ]);
        }

        $data = $this->normalizeRecetteData($data, $targetGare, (string) ($recette->service_scope ?? 'gares'));

        $recette->update($data);

        $after = $recette->fresh()->only([
            'operation_date',
            'ticket_inter_amount',
            'ticket_national_amount',
            'bagage_inter_amount',
            'bagage_national_amount',
            'amount',
            'description',
            'gare_id',
        ]);
        $hasFieldChanges = $this->hasMeaningfulChanges($before, $after);

        if ($hasFieldChanges) {
            RecetteHistory::create([
                'recette_id' => $recette->id,
                'modified_by' => $request->user()->id,
                'before' => $before,
                'after' => $after,
                'comment' => $request->string('history_comment')->toString() ?: 'Modification de recette',
            ]);
        }

        $uploadedFiles = $this->uploadedRecetteJustificatifs($request);
        if ($uploadedFiles !== []) {
            $this->replaceJustificatifs($recette);
            foreach ($uploadedFiles as $file) {
                $this->attachUploadedPiece(
                    $recette,
                    $file,
                    $request->user()->id,
                    $this->defaultRecetteJustificatifName(
                        $request->string('justificatif_name')->toString(),
                        $targetGare?->name ?? $recette->gare?->name ?? 'Gare',
                        (string) ($data['operation_date'] ?? $recette->operation_date?->toDateString() ?? '')
                    )
                );
            }
        }

        if ($hasFieldChanges) {
            $this->activity->log($request->user(), 'recette_updated', $recette, 'Modification d\'une recette.', [
                'before' => $before,
                'after' => $after,
                'gare_id' => $recette->gare_id,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges || ($uploadedFiles !== [])
            ? 'Recette modifiée.'
            : 'Aucune modification détectée sur la recette.';

        return redirect()->route('recettes.index', ['module' => $module->value])->with('status', $status);
    }

    public function unlock(UnlockRecetteRequest $request, Recette $recette): RedirectResponse
    {
        abort_unless($request->user()->canUnlockFinancialScope($recette->service_scope), 403);
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

        $before = $recette->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $recette->update([
            'force_unlocked_until' => $until,
            'unlock_reason' => $validated['unlock_reason'],
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'recette_unlocked', $recette, 'Déverrouillage superviseur d\'une recette.', [
            'before' => $before,
            'after' => $recette->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
            'gare_id' => $recette->gare_id,
        ]);

        $this->unlockNotifications->notifyStationManagerForUnlock(
            (string) ($recette->service_scope ?? 'gares'),
            (int) $recette->gare_id,
            'recette',
            (int) $recette->id,
            $recette->operation_date,
            $until,
            (string) ($validated['unlock_reason'] ?? ''),
            $request->user()
        );

        return back()->with('status', "Déverrouillage actif pour {$duration} {$unitLabel}.");
    }

    protected function normalizeRecetteData(array $data, ?Gare $gare = null, string $scope = 'gares'): array
    {
        if ($scope === 'courrier') {
            $singleAmount = (int) round((float) ($data['ticket_national_amount'] ?? $data['amount'] ?? 0), 0);

            $data['ticket_inter_amount'] = 0;
            $data['ticket_national_amount'] = $singleAmount;
            $data['bagage_inter_amount'] = 0;
            $data['bagage_national_amount'] = 0;
            $data['amount'] = $singleAmount;

            return $data;
        }

        $data['ticket_inter_amount'] = (int) round((float) ($data['ticket_inter_amount'] ?? 0), 0);
        if ($gare?->isNationalOnly()) {
            $data['ticket_inter_amount'] = 0;
        }
        $data['ticket_national_amount'] = $gare?->isInterOnly()
            ? 0
            : (int) round((float) ($data['ticket_national_amount'] ?? 0), 0);
        $data['bagage_inter_amount'] = (int) round((float) ($data['bagage_inter_amount'] ?? 0), 0);
        if ($gare?->isNationalOnly()) {
            $data['bagage_inter_amount'] = 0;
        }
        $data['bagage_national_amount'] = $gare?->isInterOnly()
            ? 0
            : (int) round((float) ($data['bagage_national_amount'] ?? 0), 0);
        $data['amount'] = $data['ticket_inter_amount']
            + $data['ticket_national_amount']
            + $data['bagage_inter_amount']
            + $data['bagage_national_amount'];

        return $data;
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

    protected function attachUploadedPiece(Recette $recette, UploadedFile $file, int $userId, ?string $desiredName = null): PieceJustificative
    {
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $directory = 'justificatifs/recettes/'.now()->format('Y/m');
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

        $piece = $recette->justificatives()->create([
            'document_type' => 'recette',
            'original_name' => $originalName,
            'file_name' => basename($path),
            'mime_type' => $mimeType,
            'size' => $size,
            'disk' => $disk,
            'path' => $path,
            'uploaded_by' => $userId,
            'uploaded_at' => now(),
        ]);

        $this->activity->log(auth()->user(), 'recette_attachment_added', $recette, 'Ajout d\'un justificatif sur une recette.', [
            'gare_id' => $recette->gare_id,
            'after' => [
                'piece' => $piece->original_name,
                'mime_type' => $piece->mime_type,
                'size' => $piece->size,
            ],
        ]);

        return $piece;
    }

    protected function replaceJustificatifs(Recette $recette): void
    {
        foreach ($recette->justificatives()->get() as $piece) {
            if ($piece->disk && $piece->path) {
                Storage::disk($piece->disk)->delete($piece->path);
            }
            $piece->delete();
        }
    }

    protected function uploadedRecetteJustificatifs(Request $request): array
    {
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

    protected function defaultRecetteJustificatifName(?string $desiredName, string $gareName, string $operationDate): string
    {
        $desired = trim((string) $desiredName);
        if ($desired !== '') {
            return $desired;
        }

        $gare = trim($gareName) !== '' ? trim($gareName) : 'Gare';
        $date = trim($operationDate) !== '' ? trim($operationDate) : now('Africa/Abidjan')->toDateString();

        return "Recette {$gare} {$date}";
    }
}

