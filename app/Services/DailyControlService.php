<?php

namespace App\Services;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Models\DailyControl;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class DailyControlService
{
    public function runForDate(string $concernedDate, ?string $serviceScope = null): Collection
    {
        $scopes = $serviceScope ? [$serviceScope] : ['gares', 'courrier'];
        $controls = collect();

        foreach ($scopes as $scope) {
            $module = $scope === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

            $scopeControls = Gare::query()
                ->where('is_active', true)
                ->get()
                ->map(function (Gare $gare) use ($concernedDate, $scope) {
                    $hasRecette = $gare->recettes()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                    $hasDepense = $gare->depenses()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                    $hasVersement = $gare->versementsBancaires()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();

                    $missing = [];
                    if (! $hasRecette) {
                        $missing[] = 'recette';
                    }
                    if (! $hasDepense) {
                        $missing[] = 'depense';
                    }
                    if (! $hasVersement) {
                        $missing[] = 'versement_bancaire';
                    }

                    return DailyControl::updateOrCreate(
                        [
                            'service_scope' => $scope,
                            'gare_id' => $gare->id,
                            'concerned_date' => $concernedDate,
                        ],
                        [
                            'control_date' => now('Africa/Abidjan')->toDateString(),
                            'has_recette' => $hasRecette,
                            'has_depense' => $hasDepense,
                            'has_versement' => $hasVersement,
                            'is_compliant' => count($missing) === 0,
                            'missing_operations' => $missing,
                        ]
                    )->load('gare');
                });

            $anomalies = $scopeControls->filter(fn (DailyControl $control) => ! $control->is_compliant)->values();
            $this->notifySupervisors($anomalies, $concernedDate, $module);
            $this->notifyOperators($anomalies, $concernedDate, $scope, $module);

            $controls = $controls->merge($scopeControls);
        }

        return $controls;
    }

    public function ensureFreshControl(?string $concernedDate = null, ?string $serviceScope = null): Collection
    {
        $date = $concernedDate ?: now('Africa/Abidjan')->subDay()->toDateString();

        return $this->runForDate($date, $serviceScope);
    }

    protected function notifySupervisors(Collection $anomalies, string $concernedDate, ServiceModule $module): void
    {
        $supervisors = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Responsable->value])
            ->where('is_active', true)
            ->get();

        if ($supervisors->isEmpty()) {
            return;
        }

        $gares = $anomalies->pluck('gare.name')->filter()->values()->all();
        $operations = $anomalies->pluck('missing_operations')->flatten()->unique()->values()->all();

        foreach ($supervisors as $supervisor) {
            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $supervisor->id,
                    'type' => $anomalies->isEmpty() ? 'daily_control_ok' : 'daily_control_alert',
                    'source_key' => 'daily-control:'.$module->value.':'.$concernedDate.':'.$supervisor->id,
                ],
                [
                    'subject' => $anomalies->isEmpty() ? 'Contrôle journalier conforme' : 'Alerte de non-saisie',
                    'content' => $anomalies->isEmpty()
                        ? sprintf('[%s] Toutes les gares actives ont renseigné leurs opérations du %s.', $module->shortLabel(), $concernedDate)
                        : sprintf('[%s] Une ou plusieurs gares n\'ont pas finalisé leurs saisies du %s.', $module->shortLabel(), $concernedDate),
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'concerned_date' => $concernedDate,
                    'gares' => $gares,
                    'operations' => array_merge($operations, [$module->value]),
                    'payload' => ['anomaly_count' => $anomalies->count(), 'module' => $module->value],
                ]
            );
        }
    }

    protected function notifyOperators(Collection $anomalies, string $concernedDate, string $scope, ServiceModule $module): void
    {
        foreach ($anomalies as $control) {
            $gare = $control->gare;
            if (! $gare) {
                continue;
            }

            $users = User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($gare, $scope) {
                    if ($scope === 'courrier') {
                        $query->where(function ($inner) use ($gare) {
                            $inner->where('role', UserRole::AgentCourrierGare->value)->where('gare_id', $gare->id);
                        })->orWhere(function ($inner) use ($gare) {
                            $inner->where('role', UserRole::CaissierCourrier->value)
                                ->whereHas('gares', fn ($q) => $q->where('gares.id', $gare->id));
                        });
                    } else {
                        $query->where(function ($inner) use ($gare) {
                            $inner->where('role', UserRole::ChefDeGare->value)->where('gare_id', $gare->id);
                        })->orWhere(function ($inner) use ($gare) {
                            $inner->whereIn('role', [UserRole::CaissierGare->value, UserRole::Caissiere->value, UserRole::ChefDeZone->value])
                                ->whereHas('gares', fn ($q) => $q->where('gares.id', $gare->id));
                        });
                    }
                })
                ->get();

            foreach ($users as $user) {
                NotificationHistory::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => 'daily_control_operator_alert',
                        'source_key' => 'daily-control-operator:'.$scope.':'.$control->id.':'.$user->id,
                    ],
                    [
                        'subject' => 'Saisie manquante à régulariser',
                        'content' => sprintf(
                            '[%s] Des opérations restent à saisir pour %s au titre du %s : %s.',
                            $module->shortLabel(),
                            $gare->name,
                            now()->parse($concernedDate)->format('d/m/Y'),
                            collect($control->missing_operations ?? [])->map(fn ($item) => str_replace('_', ' ', $item))->implode(', ')
                        ),
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'concerned_date' => $concernedDate,
                        'gares' => [$gare->name],
                        'operations' => array_merge($control->missing_operations ?? [], [$module->value]),
                        'payload' => ['module' => $module->value],
                    ]
                );
            }
        }
    }
}
