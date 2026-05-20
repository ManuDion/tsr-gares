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
use App\Services\CashierVirtualGareService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected ActivityLogService $activity,
        protected CashierVirtualGareService $virtualGares
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();
        $module = ModuleContext::fromRequest($request, $actor);

        $users = User::query()
            ->with(['primaryGare', 'gares', 'department'])
            ->with('serviceModules')
            ->when(! $actor->hasGlobalVisibility(), function ($query) use ($actor, $module) {
                if (! $actor->canManageUsersForModule($module)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $departmentCodes = $this->departmentCodesForModule($module);

                $query->where(function ($inner) use ($module, $departmentCodes) {
                    $inner->whereHas('serviceModules', fn ($modules) => $modules->where('module', $module->value))
                        ->orWhereHas('department', fn ($department) => $department->whereIn('code', $departmentCodes));
                });
            })
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
            'module' => $module,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);
        $actor = auth()->user();

        return view('users.create', [
            'moduleOptions' => $this->moduleOptionsForActor($actor),
            'roleOptionsByModule' => $this->roleOptionsByModule($actor),
            'allowNoModuleOption' => ! $actor->isServiceAdmin(),
            'forcedModule' => $actor->isServiceAdmin() ? $actor->assignedModule()?->value : null,
            'gares' => Gare::query()->where('is_active', true)->where('is_virtual', false)->orderBy('name')->get(),
            'hrServiceOptions' => $this->hrServiceOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $module = ServiceModule::tryFrom((string) ($data['module'] ?? ''));
        $role = UserRole::fromLegacyAware($data['role']);
        $modules = $this->resolveModules($request, $module);
        $allowMultiGareEntry = $request->boolean('allow_multi_gare_entry');
        $zoneGares = $this->resolveZoneGares($request, $role, $modules, $allowMultiGareEntry);
        $payload = $this->normalizePayload($request, $data, $module, $role);

        $user = User::create($payload);
        $user->gares()->sync($zoneGares);
        $this->syncUserModules($user, $modules);
        $this->ensureVirtualGaresForUser($user, $modules);
        $this->assignVirtualPrimaryGareForMultiEntryUser($user, $role, $module, $zoneGares);

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
                'contract_type' => $user->contract_type,
                'assignment_location' => $user->assignment_location,
                'hr_service' => $user->hr_service,
                'role' => $user->role?->value,
                'module' => $module?->value,
                'modules' => $modules,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'cashier_collection_mode' => $user->cashierCollectionMode(),
            ],
        ]);

        return redirect()->route('users.index')->with('status', 'Utilisateur créé. Le mot de passe devra être personnalisé à la première connexion.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);
        $actor = auth()->user();

        return view('users.edit', [
            'user' => $user->load(['gares', 'department', 'serviceModules']),
            'moduleOptions' => $this->moduleOptionsForActor($actor),
            'roleOptionsByModule' => $this->roleOptionsByModule($actor),
            'allowNoModuleOption' => ! $actor->isServiceAdmin(),
            'forcedModule' => $actor->isServiceAdmin() ? $actor->assignedModule()?->value : null,
            'gares' => Gare::query()->where('is_active', true)->where('is_virtual', false)->orderBy('name')->get(),
            'hrServiceOptions' => $this->hrServiceOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $before = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'contract_type' => $user->contract_type,
            'assignment_location' => $user->assignment_location,
            'hr_service' => $user->hr_service,
            'role' => $user->role?->value,
            'module' => $user->assignedModule()?->value,
            'modules' => $user->moduleMemberships(),
            'gare_id' => $user->gare_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'cashier_collection_mode' => $user->cashierCollectionMode(),
        ];

        $data = $request->validated();
        $module = ServiceModule::tryFrom((string) ($data['module'] ?? ''));
        $role = UserRole::fromLegacyAware($data['role']);
        $modules = $this->resolveModules($request, $module);
        $allowMultiGareEntry = $request->boolean('allow_multi_gare_entry');
        $zoneGares = $this->resolveZoneGares($request, $role, $modules, $allowMultiGareEntry);
        $payload = $this->normalizePayload($request, $data, $module, $role, $user);

        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        $user->update($payload);
        $user->gares()->sync($zoneGares);
        $this->syncUserModules($user, $modules);
        $this->ensureVirtualGaresForUser($user, $modules);
        $this->assignVirtualPrimaryGareForMultiEntryUser($user, $role, $module, $zoneGares);

        $this->activity->log($request->user(), 'user_updated', $user, 'Mise à jour d\'un utilisateur.', [
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'contract_type' => $user->contract_type,
                'assignment_location' => $user->assignment_location,
                'hr_service' => $user->hr_service,
                'role' => $user->role?->value,
                'module' => $module?->value,
                'modules' => $modules,
                'gare_id' => $user->gare_id,
                'gares' => $zoneGares,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'cashier_collection_mode' => $user->cashierCollectionMode(),
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
            'contract_type' => $user->contract_type,
            'assignment_location' => $user->assignment_location,
            'hr_service' => $user->hr_service,
            'role' => $user->role?->value,
            'module' => $user->assignedModule()?->value,
            'modules' => $user->moduleMemberships(),
            'gare_id' => $user->gare_id,
            'department_id' => $user->department_id,
            'gares' => $user->gares()->pluck('gares.id')->all(),
            'is_active' => $user->is_active,
            'cashier_collection_mode' => $user->cashierCollectionMode(),
        ];

        $userId = $user->id;

        DB::transaction(function () use ($user, $actor) {
            DB::table('notifications')->where('user_id', $user->id)->delete();
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

    protected function normalizePayload(Request $request, array $data, ?ServiceModule $module, UserRole $role, ?User $user = null): array
    {
        $data['role'] = $role;
        $data['is_active'] = $request->boolean('is_active');
        $data['must_change_password'] = $user
            ? $request->boolean('must_change_password', $user->must_change_password)
            : true;
        $data['allow_multi_gare_entry'] = $request->boolean('allow_multi_gare_entry');
        $data['cashier_collection_mode'] = $this->supportsCashierCollectionMode($role)
            ? (string) $request->input('cashier_collection_mode', User::CASHIER_COLLECTION_BOTH)
            : User::CASHIER_COLLECTION_BOTH;
        $data['contract_type'] = trim((string) ($data['contract_type'] ?? '')) ?: null;
        $data['assignment_location'] = trim((string) ($data['assignment_location'] ?? '')) ?: null;
        $data['hr_service'] = trim((string) ($data['hr_service'] ?? '')) ?: null;
        $data['department_id'] = $role->isUniversalSupervisor()
            ? null
            : ($module ? (Department::forModule($module)?->id ?? $user?->department_id) : $user?->department_id);
        unset($data['module'], $data['modules'], $data['zone_gares'], $data['all_gares']);

        if (! $role->requiresPrimaryGare()) {
            $data['gare_id'] = null;
        }

        return $data;
    }

    protected function supportsCashierCollectionMode(UserRole $role): bool
    {
        return in_array($role, [
            UserRole::CaissierGare,
            UserRole::CaissierCourrier,
            UserRole::Caissiere,
            UserRole::ChefDeZone,
        ], true);
    }

    protected function resolveZoneGares(Request $request, UserRole $role, array $modules = [], bool $allowMultiGareEntry = false): array
    {
        if (! $role->supportsMultipleGares() && ! $allowMultiGareEntry) {
            return [];
        }

        $excludeInterOnly = in_array(ServiceModule::Courrier->value, $modules, true);

        if ($request->boolean('all_gares')) {
            $query = Gare::query()->where('is_active', true)->where('is_virtual', false);
            if ($excludeInterOnly) {
                $query->where(function ($inner) {
                    $inner->whereNull('activity_mode')
                        ->orWhere('activity_mode', '!=', 'inter_only');
                });
            }

            return $query->pluck('id')->all();
        }

        $selectedIds = array_map('intval', $request->input('zone_gares', []));
        if (! $excludeInterOnly || empty($selectedIds)) {
            return $selectedIds;
        }

        return Gare::query()
            ->whereIn('id', $selectedIds)
            ->where(function ($inner) {
                $inner->whereNull('activity_mode')
                    ->orWhere('activity_mode', '!=', 'inter_only');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    protected function roleOptionsByModule(User $actor): array
    {
        $options = ['' => [
            ['value' => UserRole::Admin->value, 'label' => UserRole::Admin->label()],
            ['value' => UserRole::Responsable->value, 'label' => UserRole::Responsable->label()],
        ]] + UserRole::options();

        if (! $actor->isServiceAdmin()) {
            return $options;
        }

        $actorModule = $actor->assignedModule();
        if (! $actorModule) {
            return ['' => []];
        }

        $allowedValues = collect($actorModule->roleOptions())
            ->pluck('value')
            ->reject(fn ($value) => in_array($value, [UserRole::Admin->value, UserRole::Responsable->value], true))
            ->values()
            ->all();

        $filtered = [
            '' => [],
            $actorModule->value => collect($actorModule->roleOptions())
                ->filter(fn ($option) => in_array($option['value'], $allowedValues, true))
                ->values()
                ->all(),
        ];

        return $filtered;
    }

    protected function resolveModules(Request $request, ?ServiceModule $module): array
    {
        $actor = $request->user();
        $modules = collect($request->input('modules', []))
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->values();

        if ($module && ! $modules->contains($module->value)) {
            $modules->prepend($module->value);
        }

        if ($actor?->isServiceAdmin()) {
            $actorModule = $actor->assignedModule();

            return $actorModule ? [$actorModule->value] : [];
        }

        return $modules->unique()->take(2)->values()->all();
    }

    protected function moduleOptionsForActor(User $actor): array
    {
        if (! $actor->isServiceAdmin()) {
            return ServiceModule::options();
        }

        $module = $actor->assignedModule();
        if (! $module) {
            return [];
        }

        return array_values(array_filter(ServiceModule::options(), fn ($option) => $option['value'] === $module->value));
    }

    protected function syncUserModules(User $user, array $modules): void
    {
        $user->serviceModules()->delete();

        foreach ($modules as $module) {
            $user->serviceModules()->create(['module' => $module]);
        }
    }

    protected function ensureVirtualGaresForUser(User $user, array $modules): void
    {
        if (! $user->canActAsCashierForScope('gares') && ! $user->canActAsCashierForScope('courrier')) {
            return;
        }

        if (in_array(ServiceModule::Gares->value, $modules, true) && $user->canActAsCashierForScope('gares')) {
            $this->virtualGares->ensureForScope($user, 'gares');
        }

        if (in_array(ServiceModule::Courrier->value, $modules, true) && $user->canActAsCashierForScope('courrier')) {
            $this->virtualGares->ensureForScope($user, 'courrier');
        }
    }

    protected function assignVirtualPrimaryGareForMultiEntryUser(
        User $user,
        UserRole $role,
        ?ServiceModule $module,
        array $zoneGares
    ): void {
        if (! $role->requiresPrimaryGare() || ! $user->canUseMultiGareEntry()) {
            return;
        }

        $physicalGareIds = collect($zoneGares)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (count($physicalGareIds) <= 1) {
            return;
        }

        $scope = $module?->financialScope();
        if (! in_array($scope, ['gares', 'courrier'], true) || ! $user->canActAsChefForScope($scope)) {
            return;
        }

        $virtualGare = $this->virtualGares->ensureForScope($user, $scope);
        if ((int) $user->gare_id !== (int) $virtualGare->id) {
            $user->update(['gare_id' => $virtualGare->id]);
        }
    }

    protected function departmentCodesForModule(ServiceModule $module): array
    {
        return match ($module) {
            ServiceModule::Gares => ['GARES', 'EXP', 'FIN', 'DIR'],
            ServiceModule::Documents => ['DOCS', 'DOC', 'ADM', 'CTL'],
            ServiceModule::Courrier => ['COURRIER', 'CRR'],
            ServiceModule::Rh => ['RH'],
        };
    }

    protected function hrServiceOptions(): array
    {
        $departmentServices = Department::query()
            ->where('is_active', true)
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => trim((string) $name));

        $existingUserServices = User::query()
            ->whereNotNull('hr_service')
            ->pluck('hr_service')
            ->filter()
            ->map(fn ($name) => trim((string) $name));

        return $departmentServices
            ->merge($existingUserServices)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
