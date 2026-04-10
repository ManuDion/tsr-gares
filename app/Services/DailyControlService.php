<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DailyControl;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\User;
use App\Notifications\DailyControlAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class DailyControlService
{
    public function runForDate(string $concernedDate): Collection
    {
        $controls = Gare::query()
            ->where('is_active', true)
            ->get()
            ->map(function (Gare $gare) use ($concernedDate) {
                $hasRecette = $gare->recettes()->whereDate('operation_date', $concernedDate)->exists();
                $hasDepense = $gare->depenses()->whereDate('operation_date', $concernedDate)->exists();
                $hasVersement = $gare->versementsBancaires()->whereDate('operation_date', $concernedDate)->exists();

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

        $anomalies = $controls->filter(fn (DailyControl $control) => ! $control->is_compliant)->values();

        $this->notifySupervisors($anomalies, $concernedDate);
        $this->notifyOperators($anomalies, $concernedDate);

        return $controls;
    }

    public function ensureFreshControl(?string $concernedDate = null): Collection
    {
        $date = $concernedDate ?: now('Africa/Abidjan')->subDay()->toDateString();

        return $this->runForDate($date);
    }

    protected function notifySupervisors(Collection $anomalies, string $concernedDate): void
    {
        $supervisors = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Responsable->value])
            ->where('is_active', true)
            ->get();

        if ($supervisors->isEmpty()) {
            return;
        }

        if ($anomalies->isEmpty()) {
            foreach ($supervisors as $supervisor) {
                NotificationHistory::updateOrCreate(
                    [
                        'user_id' => $supervisor->id,
                        'type' => 'daily_control_ok',
                        'concerned_date' => $concernedDate,
                    ],
                    [
                        'subject' => 'Contrôle journalier conforme',
                        'content' => "Toutes les gares actives ont renseigné leurs opérations du {$concernedDate}.",
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'gares' => [],
                        'operations' => [],
                        'payload' => [
                            'anomaly_count' => 0,
                        ],
                    ]
                );
            }

            return;
        }

        $gares = $anomalies->pluck('gare.name')->filter()->values()->all();
        $operations = $anomalies->pluck('missing_operations')->flatten()->unique()->values()->all();

        Notification::send($supervisors, new DailyControlAlertNotification(
            concernedDate: $concernedDate,
            anomalyCount: $anomalies->count(),
            gares: $gares,
        ));

        foreach ($supervisors as $supervisor) {
            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $supervisor->id,
                    'type' => 'daily_control_alert',
                    'concerned_date' => $concernedDate,
                ],
                [
                    'subject' => 'Alerte de non-saisie',
                    'content' => "Une ou plusieurs gares n'ont pas finalisé leurs saisies du {$concernedDate}.",
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'gares' => $gares,
                    'operations' => $operations,
                    'payload' => [
                        'anomaly_count' => $anomalies->count(),
                    ],
                ]
            );
        }
    }

    protected function notifyOperators(Collection $anomalies, string $concernedDate): void
    {
        foreach ($anomalies as $control) {
            $users = User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($control) {
                    $query->where('gare_id', $control->gare_id)
                        ->orWhereHas('gares', fn ($garesQuery) => $garesQuery->where('gares.id', $control->gare_id));
                })
                ->get();

            foreach ($users as $user) {
                NotificationHistory::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => 'daily_control_station_alert',
                        'concerned_date' => $concernedDate,
                    ],
                    [
                        'subject' => 'Régularisation requise',
                        'content' => "Des saisies obligatoires du {$concernedDate} sont manquantes pour {$control->gare->name}.",
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'gares' => [$control->gare->name],
                        'operations' => $control->missing_operations ?? [],
                        'payload' => [
                            'gare_id' => $control->gare_id,
                        ],
                    ]
                );
            }
        }
    }
}
