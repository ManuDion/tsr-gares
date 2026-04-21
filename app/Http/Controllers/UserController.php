<?php

namespace App\Http\Controllers;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Gare;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['primaryGare', 'gares', 'department'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
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
            'moduleOptions' => ServiceModule::options(),
            'roleOptionsByModule' => UserRole::options(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $module = ServiceModule::from($data['module']);
        $role = UserRole::fromLegacyAware($data['role']);
        $zoneGares = $this->resolveZoneGares($request, $role);
        $payload = $this->normalizePayload($request, $data, $module, $role);

        $user = User::create($payload);
        $user->gares()->sync($zoneGares);

        if ($module === ServiceModule::Rh && in_array($role, [UserRole::ResponsableRh, UserRole::PersonnelTsr], true)) {
            Employee::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $this->extractFirstName($user->name),
                    'last_name' => $this->extractLastName($user->name),
                    'full_name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'department_id' => $user->department_id,
                    'gare_id' => $user->gare_id,
                    'hire_date' => now()->toDateString(),
                    'employment_status' => 'draft',
                ]
            );
        }

        $this->activity->log($request->user(), 'user_created', $user, 'Création d\'un utilisateur.', [
            'after' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role?->value,
                'module' => $module->value,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
            ],
        ]);

        return redirect()->route('users.index')->with('status', 'Utilisateur créé. Le mot de passe devra être personnalisé à la première connexion.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load(['gares', 'department']),
            'moduleOptions' => ServiceModule::options(),
            'roleOptionsByModule' => UserRole::options(),
            'gares' => Gare::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $before = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role?->value,
            'module' => $user->assignedModule()?->value,
            'gare_id' => $user->gare_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
        ];

        $data = $request->validated();
        $module = ServiceModule::from($data['module']);
        $role = UserRole::fromLegacyAware($data['role']);
        $zoneGares = $this->resolveZoneGares($request, $role);
        $payload = $this->normalizePayload($request, $data, $module, $role, $user);

        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        $user->update($payload);
        $user->gares()->sync($zoneGares);

        $this->activity->log($request->user(), 'user_updated', $user, 'Mise à jour d\'un utilisateur.', [
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role?->value,
                'module' => $module->value,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
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

        $actor = auth()->user();
        $snapshot = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role?->value,
            'module' => $user->assignedModule()?->value,
            'gare_id' => $user->gare_id,
            'department_id' => $user->department_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
        ];

        $userId = $user->id;

        DB::transaction(function () use ($user, $actor) {
                        DB::table('notifications')->where('notifiable_id', $user->id)->where('notifiable_type', User::class)->delete();
            DB::table('notification_histories')->where('user_id', $user->id)->delete();
            DB::table('activity_logs')->where('user_id', $user->id)->update(['user_id' => $actor->id]);
            DB::table('recettes')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('depenses')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('versement_bancaires')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('recette_histories')->where('modified_by', $user->id)->update(['modified_by' => $actor->id]);

            if (DB::getSchemaBuilder()->hasTable('depense_histories')) {
                DB::table('depense_histories')->where('modified_by', $user->id)->update(['modified_by' => $actor->id]);
            }

            if (DB::getSchemaBuilder()->hasTable('versement_bancaire_histories')) {
                DB::table('versement_bancaire_histories')->where('modified_by', $user->id)->update(['modified_by' => $actor->id]);
            }

            if (DB::getSchemaBuilder()->hasTable('employee_documents')) {
                DB::table('employee_documents')->where('uploaded_by', $user->id)->update(['uploaded_by' => $actor->id]);
            }

            DB::table('piece_justificatives')->where('uploaded_by', $user->id)->update(['uploaded_by' => $actor->id]);
            DB::table('chat_messages')->where('user_id', $user->id)->update(['user_id' => $actor->id]);
            DB::table('administrative_documents')->where('uploaded_by', $user->id)->update(['uploaded_by' => $actor->id]);

            if (DB::getSchemaBuilder()->hasTable('employees')) {
                DB::table('employees')->where('user_id', $user->id)->update(['user_id' => null]);
            }

            $user->gares()->detach();
            $user->delete();
        });

        $this->activity->log($actor, 'user_deleted', 'User', 'Suppression d\'un utilisateur.', [
            'entity_type' => 'User',
            'entity_id' => $userId ?? null,
            'subject' => $snapshot['email'],
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
        $user->update(['is_active' => ! $user->is_active]);

        $this->activity->log(auth()->user(), 'user_toggled', $user, $user->is_active ? 'Activation d\'un utilisateur.' : 'Désactivation d\'un utilisateur.', [
            'before' => $before,
            'after' => ['is_active' => $user->is_active],
        ]);

        return back()->with('status', $user->is_active ? 'Utilisateur activé.' : 'Utilisateur désactivé.');
    }

    protected function normalizePayload(Request $request, array $data, ServiceModule $module, UserRole $role, ?User $user = null): array
    {
        $data['role'] = $role;
        $data['is_active'] = $request->boolean('is_active');
        $data['must_change_password'] = $user
            ? $request->boolean('must_change_password', $user->must_change_password)
            : true;
        $data['department_id'] = Department::forModule($module)?->id ?? $user?->department_id;
        unset($data['module'], $data['zone_gares'], $data['all_gares']);

        if (! $role->requiresPrimaryGare()) {
            $data['gare_id'] = null;
        }

        return $data;
    }

    protected function resolveZoneGares(Request $request, UserRole $role): array
    {
        if (! $role->supportsMultipleGares()) {
            return [];
        }

        if ($request->boolean('all_gares')) {
            return Gare::query()->where('is_active', true)->pluck('id')->all();
        }

        return array_map('intval', $request->input('zone_gares', []));
    }

    protected function extractFirstName(string $fullName): string
    {
        return trim(explode(' ', trim($fullName))[0] ?? $fullName);
    }

    protected function extractLastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return trim(implode(' ', $parts));
    }
}
