<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UnlockDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Models\Depense;
use App\Models\DepenseHistory;
use App\Services\AccessScopeService;
use App\Services\ActivityLogService;
use App\Services\DocumentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DepenseController extends Controller
{
    public function __construct(
        protected AccessScopeService $access,
        protected DocumentAnalysisService $analysis,
        protected ActivityLogService $activity
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Depense::class);
        $user = $request->user();

        $query = Depense::query()
            ->with(['gare', 'creator', 'justificatives'])
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        $this->access->scopeForUser($query, $user);

        $query->when($user->canViewAllGares() && $request->filled('gare_id'), fn ($q) => $q->where('gare_id', $request->integer('gare_id')))
            ->when($user->canViewAllGares() && $request->filled('start_date'), fn ($q) => $q->whereDate('operation_date', '>=', $request->date('start_date')))
            ->when($user->canViewAllGares() && $request->filled('end_date'), fn ($q) => $q->whereDate('operation_date', '<=', $request->date('end_date')));

        return view('depenses.index', [
            'depenses' => $query->paginate(15)->withQueryString(),
            'gares' => $this->access->availableGares($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Depense::class);

        return view('depenses.create', [
            'gares' => $this->access->availableGares($request->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
            'initialEntries' => old('entries', [[
                'operation_date' => now()->toDateString(),
                'amount' => null,
                'motif' => null,
                'reference' => null,
                'description' => null,
                'gare_id' => null,
            ]]),
        ]);
    }

    public function store(StoreDepenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Depense::class);
        $user = $request->user();
        $entries = $request->validated('entries');

        $createdCount = DB::transaction(function () use ($entries, $request, $user) {
            $count = 0;

            foreach ($entries as $index => $entry) {
                $depense = Depense::create([
                    'gare_id' => $this->access->resolveGareIdForCreation($user, data_get($entry, 'gare_id')),
                    'operation_date' => data_get($entry, 'operation_date'),
                    'amount' => data_get($entry, 'amount'),
                    'motif' => data_get($entry, 'motif'),
                    'reference' => data_get($entry, 'reference'),
                    'description' => data_get($entry, 'description'),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                if ($request->hasFile("entries.$index.justificatif")) {
                    $file = $request->file("entries.$index.justificatif");
                    $path = $file->store('justificatifs/depenses', env('JUSTIFICATIF_PRIVATE_DISK', 'private'));

                    $piece = $depense->justificatives()->create([
                        'document_type' => 'depense',
                        'original_name' => $file->getClientOriginalName(),
                        'file_name' => basename($path),
                        'mime_type' => $file->getMimeType() ?: 'application/pdf',
                        'size' => $file->getSize(),
                        'disk' => env('JUSTIFICATIF_PRIVATE_DISK', 'private'),
                        'path' => $path,
                        'uploaded_by' => $user->id,
                        'uploaded_at' => now(),
                    ]);

                    $this->analysis->analyze($piece);
                }

                $this->activity->log($user, 'depense_created', $depense, 'Création d’une dépense.', [
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

        return redirect()->route('depenses.index')->with('status', $message);
    }

    public function edit(Depense $depense): View
    {
        $this->authorize('update', $depense);

        return view('depenses.edit', [
            'depense' => $depense->load(['gare', 'histories.modifier', 'justificatives.latestAnalysis']),
            'gares' => $this->access->availableGares(auth()->user()),
            'maxSizeKb' => (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120),
        ]);
    }

    public function update(UpdateDepenseRequest $request, Depense $depense): RedirectResponse
    {
        $this->authorize('update', $depense);

        $before = $depense->only(['operation_date', 'amount', 'motif', 'reference', 'description', 'gare_id']);
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if (! $request->user()->isChefDeGare()) {
            $data['gare_id'] = $request->integer('gare_id', $depense->gare_id);
        }

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

        if ($request->hasFile('justificatif')) {
            $file = $request->file('justificatif');
            $path = $file->store('justificatifs/depenses', env('JUSTIFICATIF_PRIVATE_DISK', 'private'));

            $piece = $depense->justificatives()->create([
                'document_type' => 'depense',
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => $file->getSize(),
                'disk' => env('JUSTIFICATIF_PRIVATE_DISK', 'private'),
                'path' => $path,
                'uploaded_by' => $request->user()->id,
                'uploaded_at' => now(),
            ]);

            $this->analysis->analyze($piece);

            $this->activity->log($request->user(), 'depense_attachment_added', $depense, 'Ajout d’un justificatif sur une dépense.', [
                'gare_id' => $depense->gare_id,
                'after' => [
                    'piece' => $piece->original_name,
                    'mime_type' => $piece->mime_type,
                    'size' => $piece->size,
                ],
            ]);
        }

        if ($hasFieldChanges) {
            $this->activity->log($request->user(), 'depense_updated', $depense, 'Modification d’une dépense.', [
                'before' => $before,
                'after' => $after,
                'gare_id' => $depense->gare_id,
                'notes' => $request->string('history_comment')->toString() ?: null,
            ]);
        }

        $status = $hasFieldChanges ? 'Dépense modifiée.' : 'Aucune modification détectée sur la dépense.';

        return redirect()->route('depenses.index')->with('status', $status);
    }

    public function unlock(UnlockDepenseRequest $request, Depense $depense): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $before = $depense->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']);

        $depense->update([
            'force_unlocked_until' => now()->addHours(24),
            'unlock_reason' => $request->validated('unlock_reason'),
            'unlocked_by' => $request->user()->id,
        ]);

        $this->activity->log($request->user(), 'depense_unlocked', $depense, 'Déverrouillage superviseur d’une dépense.', [
            'before' => $before,
            'after' => $depense->fresh()->only(['force_unlocked_until', 'unlock_reason', 'unlocked_by']),
            'gare_id' => $depense->gare_id,
        ]);

        return back()->with('status', 'Dépense déverrouillée pour 24h.');
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
}
