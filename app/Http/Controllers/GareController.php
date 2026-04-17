<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGareRequest;
use App\Http\Requests\UpdateGareRequest;
use App\Models\Gare;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GareController extends Controller
{
    public function __construct(protected ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Gare::class);

        $query = Gare::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('city', 'like', '%'.$request->string('search').'%')
                        ->orWhere('code', 'like', '%'.$request->string('search').'%');
                });
            });

        if (! $request->user()->canViewAllGares()) {
            $query->whereIn('id', $request->user()->accessibleGareIds());
        }

        $gares = $query
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('gares.index', compact('gares'));
    }

    public function create(): View
    {
        $this->authorize('create', Gare::class);

        return view('gares.create');
    }

    public function store(StoreGareRequest $request): RedirectResponse
    {
        $this->authorize('create', Gare::class);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $gare = Gare::create($data);

        $this->activity->log($request->user(), 'gare_created', $gare, 'Création d\'une gare.', [
            'gare_id' => $gare->id,
            'after' => $gare->only(['name', 'code', 'city', 'zone', 'address', 'is_active']),
        ]);

        return redirect()->route('gares.index')->with('status', 'Gare créée avec succès.');
    }

    public function show(Gare $gare): View
    {
        $this->authorize('view', $gare);

        $gare->loadCount(['recettes', 'depenses', 'versementsBancaires']);

        return view('gares.show', compact('gare'));
    }

    public function edit(Gare $gare): View
    {
        $this->authorize('update', $gare);

        return view('gares.edit', compact('gare'));
    }

    public function update(UpdateGareRequest $request, Gare $gare): RedirectResponse
    {
        $this->authorize('update', $gare);

        $before = $gare->only(['name', 'code', 'city', 'zone', 'address', 'is_active']);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $gare->update($data);

        $this->activity->log($request->user(), 'gare_updated', $gare, 'Mise à jour d\'une gare.', [
            'gare_id' => $gare->id,
            'before' => $before,
            'after' => $gare->fresh()->only(['name', 'code', 'city', 'zone', 'address', 'is_active']),
        ]);

        return redirect()->route('gares.index')->with('status', 'Gare mise à jour.');
    }

    public function destroy(Gare $gare): RedirectResponse
    {
        $this->authorize('delete', $gare);

        $snapshot = $gare->only(['name', 'code', 'city', 'zone', 'address', 'is_active']);
        $gareId = $gare->id;
        $gareName = $gare->name;
        $gare->delete();

        $this->activity->log(auth()->user(), 'gare_deleted', 'Gare', 'Suppression d\'une gare.', [
            'gare_id' => $gareId,
            'subject' => $gareName,
            'before' => $snapshot,
            'entity_type' => 'Gare',
            'entity_id' => $gareId,
        ]);

        return redirect()->route('gares.index')->with('status', 'Gare supprimée.');
    }

    public function toggleActive(Gare $gare): RedirectResponse
    {
        $this->authorize('update', $gare);

        $before = $gare->only(['is_active']);
        $gare->update([
            'is_active' => ! $gare->is_active,
        ]);

        $this->activity->log(auth()->user(), 'gare_toggled', $gare, $gare->is_active ? 'Activation d\'une gare.' : 'Désactivation d\'une gare.', [
            'gare_id' => $gare->id,
            'before' => $before,
            'after' => $gare->fresh()->only(['is_active']),
        ]);

        return back()->with('status', $gare->is_active ? 'Gare activée.' : 'Gare désactivée.');
    }
}
