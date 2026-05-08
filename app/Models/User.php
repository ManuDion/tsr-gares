<?php

namespace App\Models;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'gare_id',
        'department_id',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function primaryGare(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_id');
    }

    public function gares(): BelongsToMany
    {
        return $this->belongsToMany(Gare::class)->withTimestamps();
    }

    public function notificationHistories(): HasMany
    {
        return $this->hasMany(NotificationHistory::class);
    }

    public function serviceModules(): HasMany
    {
        return $this->hasMany(UserServiceModule::class);
    }

    public function hasGlobalVisibility(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Responsable], true);
    }

    public function canViewAllGares(): bool
    {
        return $this->hasGlobalVisibility();
    }

    public function departmentCode(): ?string
    {
        return $this->department?->code;
    }

    public function assignedModule(): ?ServiceModule
    {
        if ($this->role instanceof UserRole && $this->role->module()) {
            return $this->role->module();
        }

        return ServiceModule::fromDepartment($this->department);
    }

    public function defaultModule(): ServiceModule
    {
        if ($this->hasGlobalVisibility()) {
            return ServiceModule::Gares;
        }

        if ($this->assignedModule()) {
            return $this->assignedModule();
        }

        $memberships = $this->moduleMemberships();
        if ($memberships !== []) {
            return ServiceModule::from($memberships[0]);
        }

        return ServiceModule::Gares;
    }

    public function canAccessModule(ServiceModule $module): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        return match ($module) {
            ServiceModule::Gares => $this->canAccessGaresModule() || $this->canAccessFinancialScope('gares'),
            ServiceModule::Documents => $this->canAccessAdministrativeDocumentsModule(),
            ServiceModule::Courrier => $this->canAccessCourrierModule() || $this->canAccessFinancialScope('courrier'),
            ServiceModule::Rh => $this->canAccessRhModule(),
        };
    }

    public function accessibleModules(): array
    {
        return collect(ServiceModule::cases())
            ->filter(fn (ServiceModule $module) => $this->canAccessModule($module))
            ->values()
            ->all();
    }

    public function canAccessGaresModule(): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        if ($this->isVerificateur()) {
            return $this->assignedModule() === ServiceModule::Gares;
        }

        return in_array($this->role, [
            UserRole::ChefDeGare,
            UserRole::CaissierGare,
            UserRole::Caissiere,
            UserRole::ChefDeZone,
        ], true);
    }

    public function canAccessAdministrativeDocumentsModule(): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        return $this->role === UserRole::Controleur;
    }

    public function canAccessCourrierModule(): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        if ($this->isVerificateur()) {
            return $this->assignedModule() === ServiceModule::Courrier;
        }

        return in_array($this->role, [UserRole::AgentCourrierGare, UserRole::CaissierCourrier], true);
    }

    public function canAccessRhModule(): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        return in_array($this->role, [UserRole::ResponsableRh, UserRole::PersonnelTsr], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isResponsable(): bool
    {
        return $this->role === UserRole::Responsable;
    }

    public function isVerificateur(): bool
    {
        return $this->role === UserRole::Verificateur;
    }

    public function isChefDeGare(): bool
    {
        return $this->role === UserRole::ChefDeGare;
    }

    public function isCaissierGare(): bool
    {
        return in_array($this->role, [UserRole::CaissierGare, UserRole::Caissiere, UserRole::ChefDeZone], true);
    }

    public function isControleur(): bool
    {
        return $this->role === UserRole::Controleur;
    }

    public function isAgentCourrierGare(): bool
    {
        return $this->role === UserRole::AgentCourrierGare;
    }

    public function isCaissierCourrier(): bool
    {
        return $this->role === UserRole::CaissierCourrier;
    }

    public function isResponsableRh(): bool
    {
        return $this->role === UserRole::ResponsableRh;
    }

    public function isPersonnelTsr(): bool
    {
        return $this->role === UserRole::PersonnelTsr;
    }

    public function canCreateFinancialEntry(?string $scope = null): bool
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();

        return $this->canAccessFinancialScope($scope)
            && match ($scope) {
                'gares' => $this->canActAsChefForScope('gares') || $this->canActAsCashierForScope('gares'),
                'courrier' => $this->canActAsChefForScope('courrier') || $this->canActAsCashierForScope('courrier'),
                default => false,
            };
    }

    public function canSuperviseFinancialScope(?string $scope = null): bool
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();

        if ($this->isAdmin() || $this->isResponsable()) {
            return true;
        }

        return $this->isVerificateur() && $this->canAccessFinancialScope($scope);
    }

    public function accessibleGareIds(?string $scope = null): array
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();

        if ($this->canViewAllGares()) {
            return Gare::query()->pluck('id')->all();
        }

        $ids = [];

        if ($this->isVerificateur() && $this->canAccessFinancialScope($scope)) {
            $ids = $this->gares()->pluck('gares.id')->all();
        } elseif ($this->canActAsChefForScope($scope)) {
            $ids = array_values(array_filter([$this->gare_id]));
        } elseif ($this->canActAsCashierForScope($scope)) {
            $ids = $this->gares()->pluck('gares.id')->all();
        }

        return array_values(array_unique(array_merge($ids, $this->virtualGareIdsForScope($scope))));
    }

    public function hasAccessToGare(int $gareId, ?string $scope = null): bool
    {
        if ($this->canViewAllGares()) {
            return true;
        }

        return in_array($gareId, $this->accessibleGareIds($scope), true);
    }

    public function roleLabel(): string
    {
        return $this->role?->label() ?? '-';
    }

    public function moduleLabel(): string
    {
        return $this->assignedModule()?->shortLabel() ?? 'Universel';
    }

    public function canAccessFinancialScope(?string $scope = null): bool
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();
        if (! in_array($scope, ['gares', 'courrier'], true)) {
            return false;
        }

        $module = $scope === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

        if ($this->assignedModule() === $module) {
            return true;
        }

        return in_array($module->value, $this->moduleMemberships(), true);
    }

    public function moduleMemberships(): array
    {
        if (! $this->exists) {
            return [];
        }

        return $this->relationLoaded('serviceModules')
            ? $this->serviceModules->pluck('module')->filter()->unique()->values()->all()
            : $this->serviceModules()->pluck('module')->filter()->unique()->values()->all();
    }

    public function canActAsChefForScope(string $scope): bool
    {
        return match ($scope) {
            'gares' => $this->isChefDeGare() || ($this->isAgentCourrierGare() && $this->canAccessFinancialScope('gares')),
            'courrier' => $this->isAgentCourrierGare() || ($this->isChefDeGare() && $this->canAccessFinancialScope('courrier')),
            default => false,
        };
    }

    public function canActAsCashierForScope(string $scope): bool
    {
        return match ($scope) {
            'gares' => $this->isCaissierGare() || ($this->isCaissierCourrier() && $this->canAccessFinancialScope('gares')),
            'courrier' => $this->isCaissierCourrier() || ($this->isCaissierGare() && $this->canAccessFinancialScope('courrier')),
            default => false,
        };
    }

    public function virtualGareIdsForScope(?string $scope = null): array
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();

        if (! $scope || ! $this->exists) {
            return [];
        }

        return Gare::query()
            ->where('is_virtual', true)
            ->where('virtual_owner_user_id', $this->id)
            ->where('virtual_scope', $scope)
            ->pluck('id')
            ->all();
    }
}
