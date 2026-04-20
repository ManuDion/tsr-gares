<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\User;
use App\Models\VersementBancaire;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $gares = collect([
            ['code' => 'ABJ-CENTRE', 'name' => 'Gare Abidjan Centre', 'city' => 'Abidjan', 'zone' => 'Sud'],
            ['code' => 'YAM-PRINC', 'name' => 'Gare Yamoussoukro', 'city' => 'Yamoussoukro', 'zone' => 'Centre'],
            ['code' => 'BKE-PRINC', 'name' => 'Gare Bouaké', 'city' => 'Bouaké', 'zone' => 'Centre'],
            ['code' => 'YOP-PRINC', 'name' => 'Gare Yopougon', 'city' => 'Yopougon', 'zone' => 'Sud'],
            ['code' => 'KGO-PRINC', 'name' => 'Gare Korhogo', 'city' => 'Korhogo', 'zone' => 'Nord'],
        ])->map(fn (array $gare) => Gare::create($gare));

        $admin = User::create([
            'name' => 'Admin TSR',
            'email' => 'admin@tsr.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Responsable TSR',
            'email' => 'responsable@tsr.test',
            'password' => 'password',
            'role' => UserRole::Responsable,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Chef Gare Abidjan',
            'email' => 'chef.gare@tsr.test',
            'password' => 'password',
            'role' => UserRole::ChefDeGare,
            'gare_id' => $gares[0]->id,
            'is_active' => true,
        ]);

        $caissiere = User::create([
            'name' => 'Caissière Centre',
            'email' => 'caissiere@tsr.test',
            'password' => 'password',
            'role' => UserRole::Caissiere,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Contrôleur TSR',
            'email' => 'controleur@tsr.test',
            'password' => 'password',
            'role' => UserRole::Controleur,
            'is_active' => true,
        ]);

        $caissiere->gares()->sync([$gares[1]->id, $gares[2]->id, $gares[3]->id]);

        foreach (range(0, 6) as $index) {
            $date = now()->subDays($index)->toDateString();

            foreach ($gares as $gare) {
                $ticketInter = 45000 + ($gare->id * 3000) + ($index * 1000);
                $ticketNational = 30000 + ($gare->id * 1800) + ($index * 800);
                $bagageInter = 12000 + ($gare->id * 600) + ($index * 250);
                $bagageNational = 8000 + ($gare->id * 400) + ($index * 150);

                Recette::create([
                    'gare_id' => $gare->id,
                    'operation_date' => $date,
                    'ticket_inter_amount' => $ticketInter,
                    'ticket_national_amount' => $ticketNational,
                    'bagage_inter_amount' => $bagageInter,
                    'bagage_national_amount' => $bagageNational,
                    'amount' => $ticketInter + $ticketNational + $bagageInter + $bagageNational,
                    'reference' => null,
                    'description' => 'Recette journalière',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                    'created_at' => now()->subDays($index),
                    'updated_at' => now()->subDays($index),
                ]);

                Depense::create([
                    'gare_id' => $gare->id,
                    'operation_date' => $date,
                    'amount' => 12000 + ($gare->id * 1800) + ($index * 900),
                    'motif' => 'Frais opérationnels',
                    'reference' => 'DEP-'.$gare->code.'-'.$index,
                    'description' => 'Dépense journalière',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                    'created_at' => now()->subDays($index),
                    'updated_at' => now()->subDays($index),
                ]);

                if (! ($gare->code === 'BKE-PRINC' && $index === 1)) {
                    VersementBancaire::create([
                        'gare_id' => $gare->id,
                        'operation_date' => $date,
                        'receipt_date' => $date,
                        'amount' => 45000 + ($gare->id * 3000) + ($index * 700),
                        'reference' => 'VRS-'.$gare->code.'-'.$index,
                        'bank_name' => 'Banque partenaire',
                        'description' => 'Versement du jour',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                        'created_at' => now()->subDays($index),
                        'updated_at' => now()->subDays($index),
                    ]);
                }
            }
        }
    }
}
