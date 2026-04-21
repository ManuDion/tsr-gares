<?php

namespace App\Models;

use App\Enums\ServiceModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function originatedCourriers(): HasMany
    {
        return $this->hasMany(Courrier::class, 'origin_department_id');
    }

    public function destinationCourriers(): HasMany
    {
        return $this->hasMany(Courrier::class, 'destination_department_id');
    }

    public function module(): ?ServiceModule
    {
        return ServiceModule::fromDepartment($this);
    }

    public function moduleLabel(): string
    {
        return $this->module()?->label() ?? $this->name;
    }

    public static function forModule(ServiceModule $module): ?self
    {
        return static::query()
            ->whereIn('code', [$module->departmentCode(), ...match ($module) {
                ServiceModule::Gares => ['EXP', 'FIN', 'DIR'],
                ServiceModule::Documents => ['ADM', 'CTL', 'DOC'],
                ServiceModule::Courrier => ['CRR'],
                ServiceModule::Rh => ['RH'],
            }])
            ->orderByRaw("CASE WHEN code = ? THEN 0 ELSE 1 END", [$module->departmentCode()])
            ->first();
    }
}
