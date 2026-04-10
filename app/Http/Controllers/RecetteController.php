<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecetteRequest;
use App\Http\Requests\UnlockRecetteRequest;
use App\Http\Requests\UpdateRecetteRequest;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\RecetteHistory;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecetteController extends Controller
{
    public function __construct(protected AccessScopeService $access) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recette::class);
        $user = $request->user();

        $query = Recette::query()
            ->with(['gare', 'creator', 'updater'])
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

        Recette::create($data);

        return redirect()->route('recettes.index')->with('status', 'Recette enregistrée.');
    }

    public function edit(Recette $recette): View
    {
        $this->authorize('update', $recette);

        return view('recettes.edit', [
            'recette' => $recette->load(['gare', 'histories.modifier']),
            'gares' => $this->access->availableGares(auth()->user()),
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

        RecetteHistory::create([
            'recette_id' => $recette->id,
            'modified_by' => $request->user()->id,
            'before' => $before,
            'after' => $recette->fresh()->only(['operation_date', 'amount', 'reference', 'description', 'gare_id']),
            'comment' => $request->string('history_comment')->toString() ?: 'Modification de recette',
        ]);

        return redirect()->route('recettes.index')->with('status', 'Recette modifiée.');
    }

    public function unlock(UnlockRecetteRequest $request, Recette $recette): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $recette->update([
            'force_unlocked_until' => now()->addHours(24),
            'unlock_reason' => $request->validated('unlock_reason'),
            'unlocked_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Recette déverrouillée pour 24h.');
    }
}
