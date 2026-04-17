<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecetteRequest;
use App\Http\Requests\UnlockRecetteRequest;
use App\Http\Requests\UpdateRecetteRequest;
use App\Models\PieceJustificative;
use App\Models\Recette;
use App\Models\RecetteHistory;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\DocumentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecetteController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected ActivityLogService $activity,
        protected DocumentAnalysisService $analysis
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recette::class);
        $user = $request->user();

        $query = Recette::query()
            ->with(['gare', 'creator', 'updater', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $this->access->scopeForUser($query, $user);

        $query->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', (int) $request->integer('gare_id')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')));

        return view('recettes.index', [
            'recettes' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Recette::class);

        return view('recettes.create', [
            'gares' => $this->access->availableGares($request->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function store(StoreRecetteRequest $request): RedirectResponse
    {
        $this->authorize('create', Recette::class);
        $user = $request->user();
        $data = $request->validated();

        $data['gare_id'] = $this->access->resolveGareIdForCreation($user, $request->integer('gare_id'));
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        $recette = Recette::create($data);

        if ($request->hasFile('justificatif')) {
            $piece = $this->attachUploadedPiece($recette, $request->file('justificatif'), $user->id);
            $this->analysis->analyze($piece);
        }

        $this->activity->log($user, 'recette_created', $recette, 'Création d\'une recette.', [
            'before' => null,
            'after' => $recette->only(['gare_id', 'operation_date', 'amount', 'reference', 'description']),
            'gare_id' => $recette->gare_id,
        ]);

        return redirect()->route('recettes.index')->with('status', 'Recette enregistrée.');
    }

    public function edit(Recette $recette): View
    {
        $this->authorize('update', $recette);

        return view('recettes.edit', [
            'recette' => $recette->load(['gare', 'histories.modifier', 'justificatives.latestAnalysis']),
            'gares' => $this->access->availableGares(auth()->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function update(UpdateRecetteRequest $request, Recette $recette): RedirectResponse
    {
        $this->authorize('update', $recette);

        $before = $recette->only(['operation_date', 'amount', 'reference', 'description', 'gare_id']);
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if (! $request->user()->isChefDeGare()) {
            $data['gare_id'] = $request->integer('gare_id', $recette->gare_id);
        }

        $recette->update($data);

        $after = $recette->fresh()->only(['operation_date', 'amount', 'reference', 'description', 'gare_id']);
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

        if ($request->hasFile('justificatif')) {
            $piece = $this->attachUploadedPiece($recette, $request->file('justificatif'), $request->user()->id);
            $this->analysis->analyze($piece);
        }

        if ($hasFieldChanges) {
            $this->activity->log($request->user(), 'recette_updated', $recette, 'Modification d\'une recette.', [
                'before' => $before,
                'after' => $after,
                'gare_id' => $recette->gare_id,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges ? 'Recette modifiée.' : 'Aucune modification détectée sur la recette.';

        return redirect()->route('recettes.index')->with('status', $status);
    }

    public function unlock(UnlockRecetteRequest $request, Recette $recette): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $before = $recette->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $recette->update([
            'force_unlocked_until' => now()->addHours(24),
            'unlock_reason' => $request->validated('unlock_reason'),
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'recette_unlocked', $recette, 'Déverrouillage superviseur d\'une recette.', [
            'before' => $before,
            'after' => $recette->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
            'gare_id' => $recette->gare_id,
        ]);

        return back()->with('status', 'Recette déverrouillée pour 24h.');
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

    protected function attachUploadedPiece(Recette $recette, $file, int $userId): PieceJustificative
    {
        $disk = env('JUSTIFICATIF_PRIVATE_DISK', 'private');
        $path = $file->store('justificatifs/recettes/'.now()->format('Y/m'), $disk);

        $piece = $recette->justificatives()->create([
            'document_type' => 'recette',
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'size' => $file->getSize(),
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
}
