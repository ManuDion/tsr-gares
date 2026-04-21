<?php

namespace Database\Seeders;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'code' => ServiceModule::Gares->departmentCode(),
                'name' => ServiceModule::Gares->label(),
                'description' => 'Module principal de gestion des gares : recettes, dépenses, versements et vérifications.',
            ],
            [
                'code' => ServiceModule::Documents->departmentCode(),
                'name' => ServiceModule::Documents->label(),
                'description' => 'Service de gestion des documents administratifs et réglementaires.',
            ],
            [
                'code' => ServiceModule::Courrier->departmentCode(),
                'name' => ServiceModule::Courrier->label(),
                'description' => 'Service courrier fonctionnant avec la même logique métier que le service de gestion des gares.',
            ],
            [
                'code' => ServiceModule::Rh->departmentCode(),
                'name' => ServiceModule::Rh->label(),
                'description' => 'Socle de préparation du module RH et du cycle administratif du personnel.',
            ],
        ])->each(function (array $department) {
            Department::updateOrCreate(['code' => $department['code']], $department + ['is_active' => true]);
        });

        $adminEmail = env('APP_ADMIN_EMAIL');
        $adminPassword = env('APP_ADMIN_PASSWORD');
        $adminName = env('APP_ADMIN_NAME', 'Administrateur TSR');
        $adminPhone = env('APP_ADMIN_PHONE', '+225 0000000000');

        if ($adminEmail && $adminPassword) {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'phone' => $adminPhone,
                    'password' => $adminPassword,
                    'role' => UserRole::Admin,
                    'department_id' => Department::forModule(ServiceModule::Gares)?->id,
                    'is_active' => true,
                    'must_change_password' => false,
                ]
            );
        } else {
            $this->command?->warn('Aucun compte administrateur n\'a été créé automatiquement. Renseignez APP_ADMIN_EMAIL et APP_ADMIN_PASSWORD dans le .env avant de lancer le seeder.');
        }
    }
}
