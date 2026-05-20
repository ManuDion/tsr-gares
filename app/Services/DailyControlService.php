<?php

namespace App\Services;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Models\CashierReceiptConfirmation;
use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\User;
use App\Models\VersementBancaire;
use Illuminate\Support\Collection;

class DailyControlService
{
    public function runForDate(string $concernedDate, ?string $serviceScope = null): Collection
    {
        $scopes = $serviceScope ? [$serviceScope] : ['gares', 'courrier'];
        $controls = collect();

        foreach ($scopes as $scope) {
            $module = $scope === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;
            $virtualGareByCashier = [];

            $scopeControls = Gare::query()
                ->where('is_active', true)
                ->get()
                ->map(function (Gare $gare) use ($concernedDate, $scope, &$virtualGareByCashier) {
                    $hasCashierValidation = false;
                    $hasCashierVirtualDepense = false;
                    $hasCashierVirtualVersement = false;
                    if ($gare->is_virtual) {
                        $hasRecette = CashierReceiptConfirmation::query()
                            ->where('service_scope', $scope)
                            ->where('cashier_id', $gare->virtual_owner_user_id)
                            ->whereDate('operation_date', $concernedDate)
                            ->where('is_verified', true)
                            ->exists();
                        $hasDepense = $gare->depenses()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                        $hasVersement = $gare->versementsBancaires()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                    } else {
                        $hasRecette = $gare->recettes()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                        $hasDepense = $gare->depenses()->where('service_scope', $scope)->whereDate('operation_date', $concernedDate)->exists();
                        $hasCashierValidation = $gare->cashierConfirmations()
                            ->where('service_scope', $scope)
                            ->whereDate('operation_date', $concernedDate)
                            ->where('is_verified', true)
                            ->exists();

                        if (($gare->versement_mode ?? 'direct') === 'cashier' && (int) ($gare->cashier_user_id ?? 0) > 0) {
                            $cashierId = (int) $gare->cashier_user_id;
                            if (! array_key_exists($cashierId, $virtualGareByCashier)) {
                                $virtualGareByCashier[$cashierId] = Gare::query()
                                    ->where('is_virtual', true)
                                    ->where('virtual_scope', $scope)
                                    ->where('virtual_owner_user_id', $cashierId)
                                    ->first(['id']);
                            }

                            $virtualGare = $virtualGareByCashier[$cashierId];
                            if ($virtualGare) {
                                $hasCashierVirtualDepense = Depense::query()
                                    ->where('service_scope', $scope)
                                    ->where('gare_id', $virtualGare->id)
                                    ->whereDate('operation_date', $concernedDate)
                                    ->exists();

                                $hasCashierVirtualVersement = VersementBancaire::query()
                                    ->where('service_scope', $scope)
                                    ->where('gare_id', $virtualGare->id)
                                    ->whereDate('operation_date', $concernedDate)
                                    ->exists();
                            }

                            $hasDepense = $hasDepense || $hasCashierVirtualDepense;
                            $hasVersement = $hasCashierValidation || $hasCashierVirtualVersement;
                        } else {
                            $hasVersement = $gare->versementsBancaires()
                                ->where('service_scope', $scope)
                                ->whereDate('operation_date', $concernedDate)
                                ->exists();
                        }
                    }

                    $missing = [];
                    if (! $hasRecette) {
                        $missing[] = 'recette';
                    }
                    if (! $hasDepense) {
                        $missing[] = 'depense';
                    }
                    if ($gare->versement_mode === 'cashier') {
                        if (! $hasCashierValidation && ! $hasCashierVirtualVersement && ! $hasCashierVirtualDepense) {
                            $missing[] = 'validation_caissier';
                        }
                    } elseif (! $hasVersement) {
                        $missing[] = 'versement_bancaire';
                    }

                    if ($gare->is_virtual
                        && (int) ($gare->virtual_owner_user_id ?? 0) <= 0
                        && ! $hasRecette
                        && ! $hasDepense
                        && ! $hasVersement
                    ) {
                        $missing = [];
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
        $scope = $module === ServiceModule::Courrier ? 'courrier' : 'gares';

        $supervisors = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canSuperviseFinancialScope($scope))
            ->values();

        if ($supervisors->isEmpty()) {
            return;
        }

        foreach ($supervisors as $supervisor) {
            $userAnomalies = $this->visibleAnomaliesForSupervisor($anomalies, $supervisor, $scope);
            $gares = $userAnomalies->pluck('gare.name')->filter()->values()->all();
            $operations = $userAnomalies->pluck('missing_operations')->flatten()->unique()->values()->all();

            if ($supervisor->isVerificateur() && $userAnomalies->isEmpty()) {
                continue;
            }

            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $supervisor->id,
                    'type' => $userAnomalies->isEmpty() ? 'daily_control_ok' : 'daily_control_alert',
                    'source_key' => 'daily-control:'.$module->value.':'.$concernedDate.':'.$supervisor->id,
                ],
                [
                    'subject' => $userAnomalies->isEmpty() ? 'Contrôle journalier conforme' : 'Alerte de non-saisie',
                    'content' => $userAnomalies->isEmpty()
                        ? sprintf('[%s] Toutes les gares actives ont renseigné leurs opérations du %s.', $module->shortLabel(), $concernedDate)
                        : sprintf('[%s] Une ou plusieurs gares n\'ont pas finalisé leurs saisies du %s.', $module->shortLabel(), $concernedDate),
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'concerned_date' => $concernedDate,
                    'gares' => $gares,
                    'operations' => array_merge($operations, [$module->value]),
                    'payload' => [
                        'anomaly_count' => $userAnomalies->count(),
                        'module' => $module->value,
                        'gare_ids' => $userAnomalies->pluck('gare_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    ],
                ]
            );
        }
    }

    protected function visibleAnomaliesForSupervisor(Collection $anomalies, User $supervisor, string $scope): Collection
    {
        if ($supervisor->canViewAllGares($scope)) {
            return $anomalies;
        }

        $allowedGareIds = $supervisor->accessibleGareIds($scope);

        return $anomalies
            ->filter(fn (DailyControl $control) => in_array((int) $control->gare_id, $allowedGareIds, true))
            ->values();
    }

    protected function notifyOperators(Collection $anomalies, string $concernedDate, string $scope, ServiceModule $module): void
    {
        foreach ($anomalies as $control) {
            $gare = $control->gare;
            if (! $gare || ! $gare->is_active) {
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
                            collect($control->missing_operations ?? [])->map(function ($item) {
                                return match ($item) {
                                    'validation_caissier' => 'validation caissier',
                                    default => str_replace('_', ' ', $item),
                                };
                            })->implode(', ')
                        ),
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'concerned_date' => $concernedDate,
                        'gares' => [$gare->name],
                        'operations' => array_merge($control->missing_operations ?? [], [$module->value]),
                        'payload' => [
                            'module' => $module->value,
                            'daily_control_id' => (int) $control->id,
                            'gare_id' => (int) $gare->id,
                        ],
                    ]
                );
            }
        }
    }
}
