<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Gare;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['primaryGare', 'gares'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('email', 'like', '%'.$request->string('search').'%');
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => UserRole::options(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $zoneGares = $this->resolveZoneGares($request, $data);
        $data = $this->normalizePayload($request, $data);

        $user = User::create($data);
        $user->gares()->sync($zoneGares);

        $this->activity->log($request->user(), 'user_created', $user, 'Création d\'un utilisateur.', [
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
            ],
        ]);

        return redirect()->route('users.index')->with('status', 'Utilisateur créé.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('gares'),
            'roles' => UserRole::options(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'gare_id' => $user->gare_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
        ];

        $data = $request->validated();
        $zoneGares = $this->resolveZoneGares($request, $data);
        $data = $this->normalizePayload($request, $data, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $user->gares()->sync($zoneGares);

        $this->activity->log($request->user(), 'user_updated', $user, 'Mise à jour d\'un utilisateur.', [
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
            ],
        ]);

        return redirect()->route('users.index')->with('status', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'gare_id' => $user->gare_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
        ];

        $userId = $user->id;
        $subject = $user->email;
        $user->delete();

        $this->activity->log(auth()->user(), 'user_deleted', 'User', 'Suppression d\'un utilisateur.', [
            'entity_type' => 'User',
            'entity_id' => $userId,
            'subject' => $subject,
            'before' => $snapshot,
        ]);

        return redirect()->route('users.index')->with('status', 'Utilisateur supprimé.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->is(auth()->user()) && $user->is_active) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $before = ['is_active' => $user->is_active];
        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $this->activity->log(auth()->user(), 'user_toggled', $user, $user->is_active ? 'Activation d\'un utilisateur.' : 'Désactivation d\'un utilisateur.', [
            'before' => $before,
            'after' => ['is_active' => $user->is_active],
        ]);

        return back()->with('status', $user->is_active ? 'Utilisateur activé.' : 'Utilisateur désactivé.');
    }

    protected function normalizePayload(Request $request, array $data, ?User $user = null): array
    {
        $role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);

        $data['is_active'] = $request->boolean('is_active');

        if ($role !== UserRole::ChefDeGare) {
            $data['gare_id'] = null;
        }

        if ($role !== UserRole::Caissiere && $role !== UserRole::ChefDeZone) {
            unset($data['zone_gares']);
        }

        return $data;
    }

    protected function resolveZoneGares(Request $request, array $data): array
    {
        $role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);

        if ($role !== UserRole::Caissiere && $role !== UserRole::ChefDeZone) {
            return [];
        }

        if ($request->boolean('all_gares')) {
            return Gare::query()->where('is_active', true)->pluck('id')->all();
        }

        return array_map('intval', $data['zone_gares'] ?? []);
    }
}
