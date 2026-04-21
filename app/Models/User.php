<?php

namespace App\Models;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function notificationHistories()
    {
        return $this->hasMany(NotificationHistory::class);
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

        return $this->assignedModule() ?? ServiceModule::Gares;
    }

    public function canAccessModule(ServiceModule $module): bool
    {
        if ($this->hasGlobalVisibility()) {
            return true;
        }

        return match ($module) {
            ServiceModule::Gares => $this->canAccessGaresModule(),
            ServiceModule::Documents => $this->canAccessAdministrativeDocumentsModule(),
            ServiceModule::Courrier => $this->canAccessCourrierModule(),
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

        return match ($scope) {
            'gares' => $this->isChefDeGare() || $this->isCaissierGare(),
            'courrier' => $this->isAgentCourrierGare() || $this->isCaissierCourrier(),
            default => false,
        };
    }

    public function accessibleGareIds(?string $scope = null): array
    {
        $scope = $scope ?: $this->defaultModule()->financialScope();

        if ($this->canViewAllGares()) {
            return Gare::query()->pluck('id')->all();
        }

        if ($scope === 'gares') {
            if ($this->isChefDeGare()) {
                return array_values(array_filter([$this->gare_id]));
            }

            if ($this->isCaissierGare()) {
                return $this->gares()->pluck('gares.id')->all();
            }
        }

        if ($scope === 'courrier') {
            if ($this->isAgentCourrierGare()) {
                return array_values(array_filter([$this->gare_id]));
            }

            if ($this->isCaissierCourrier()) {
                return $this->gares()->pluck('gares.id')->all();
            }
        }

        return [];
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
        return $this->role?->label() ?? '—';
    }

    public function moduleLabel(): string
    {
        return $this->assignedModule()?->shortLabel() ?? 'Universel';
    }
}
