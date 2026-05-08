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
use App\Services\CashierVirtualGareService;
use App\Support\UploadedFileName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VersementBancaireController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected ActivityLogService $activity,
        protected CashierVirtualGareService $virtualGares
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VersementBancaire::class);
        $user = $request->user();

        $query = VersementBancaire::query()
            ->with(['gare', 'creator', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows() && $user->canAccessModule($module), 403);
        $serviceScope = ModuleContext::financialScope($module);

        $this->access->scopeForUser($query, $user, 'gare_id', $serviceScope);

        $query->when($user->canViewAllGares() && $request->filled('gare_id'), fn ($q) => $q->where('gare_id', $request->integer('gare_id')))
            ->when($user->canViewAllGares() && $request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($user->canViewAllGares() && $request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')));

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

        return view('versements.create', [
            'gares' => $gares,
            'module' => $module,
            'virtualGare' => $virtualGare,
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
        if ($targetGare?->isInterOnly()) {
            $data['account_type'] = 'inter';
        }

        $data['bank_name'] = $this->normalizedBankName($data['account_type'] ?? null, $data['bank_name'] ?? null);

        unset($data['bordereau'], $data['bordereau_name']);

        $versement = VersementBancaire::create($data);

        if ($request->hasFile('bordereau')) {
            $this->attachUploadedPiece(
                $versement,
                $request->file('bordereau'),
                $user->id,
                $request->string('bordereau_name')->toString()
            );
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

        return view('versements.edit', [
            'versement' => $versement->load(['gare', 'histories.modifier', 'justificatives']),
            'gares' => $this->access->availableGares(auth()->user(), $versement->service_scope ?? 'gares'),
            'module' => $module,
            'virtualGare' => auth()->user()->canActAsCashierForScope($versement->service_scope ?? 'gares')
                ? $this->virtualGares->ensureForScope(auth()->user(), $versement->service_scope ?? 'gares')
                : null,
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
        } elseif (! $request->user()->canActAsChefForScope((string) ($versement->service_scope ?? 'gares'))) {
            $targetGareId = $request->integer('gare_id', $versement->gare_id);
            $data['gare_id'] = $targetGareId;
        }

        $targetGare = Gare::query()->find($targetGareId);
        if (! $virtualGare && $targetGare && ($targetGare->versement_mode ?? 'direct') === 'cashier') {
            throw ValidationException::withMessages([
                'gare_id' => 'Cette gare est rattachee a un caissier. Le versement se fait uniquement au niveau du caissier.',
            ]);
        }

        if ($targetGare?->isInterOnly()) {
            $data['account_type'] = 'inter';
        }
        $data['bank_name'] = $this->normalizedBankName($data['account_type'] ?? null, $data['bank_name'] ?? null);

        unset($data['bordereau'], $data['bordereau_name']);

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

        if ($request->hasFile('bordereau')) {
            $piece = $this->attachUploadedPiece(
                $versement,
                $request->file('bordereau'),
                $request->user()->id,
                $request->string('bordereau_name')->toString()
            );

            $this->activity->log($request->user(), 'versement_attachment_added', $versement, 'Ajout d\'un bordereau sur un versement bancaire.', [
                'gare_id' => $versement->gare_id,
                'after' => [
                    'piece' => $piece->original_name,
                    'mime_type' => $piece->mime_type,
                    'size' => $piece->size,
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

        $status = $hasFieldChanges || $request->hasFile('bordereau')
            ? 'Versement modifié.'
            : 'Aucune modification détectée sur le versement.';

        return redirect()->route('versements.index', ['module' => $module->value])->with('status', $status);
    }

    public function unlock(UnlockVersementBancaireRequest $request, VersementBancaire $versement): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $before = $versement->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $versement->update([
            'force_unlocked_until' => now()->addHours(24),
            'unlock_reason' => $request->validated('unlock_reason'),
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'versement_unlocked', $versement, 'Déverrouillage superviseur d\'un versement.', [
            'gare_id' => $versement->gare_id,
            'before' => $before,
            'after' => $versement->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
        ]);

        return back()->with('status', 'Versement déverrouillé pour 24h.');
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
        $path = $file->store('justificatifs/versements/'.now()->format('Y/m'), $disk);

        return $versement->justificatives()->create([
            'document_type' => 'versement_bancaire',
            'original_name' => UploadedFileName::build($desiredName, $file),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'size' => $file->getSize(),
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
}
