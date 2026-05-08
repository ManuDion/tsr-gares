<?php

namespace App\Services;

use App\Enums\ServiceModule;
use App\Enums\UserRole;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Models\Recette;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Models\VersementBancaire;
use Illuminate\Support\Collection;

class VerificationService
{
    public function __construct(
        protected ActivityLogService $activity,
        protected CashierFlowService $cashierFlow,
        protected CashierVirtualGareService $virtualGares
    ) {
    }

    public function runForDate(string $operationDate, ?string $serviceScope = null): Collection
    {
        $scopes = $serviceScope ? [$serviceScope] : ['gares', 'courrier'];
        $checks = collect();

        foreach ($scopes as $scope) {
            $module = $scope === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

            $physicalChecks = Gare::query()
                ->where('is_active', true)
                ->where('is_virtual', false)
                ->get()
                ->map(function (Gare $gare) use ($operationDate, $scope) {
                    $metrics = $this->cashierFlow->expectedForGareDate($gare, $scope, $operationDate);
                    $controlMode = $gare->versement_mode === 'cashier' ? 'cashier_transfer' : 'direct';

                    if ($controlMode === 'cashier_transfer') {
                        $confirmation = $gare->cashierConfirmations()
                            ->where('service_scope', $scope)
                            ->whereDate('operation_date', $operationDate)
                            ->latest('id')
                            ->first();

                        $versementsInter = (float) ($confirmation?->received_inter_total ?? 0);
                        $versementsNational = (float) ($confirmation?->received_national_total ?? 0);
                        $versementsTotal = round($versementsInter + $versementsNational, 2);
                    } else {
                        $versementsInter = (float) VersementBancaire::query()
                            ->where('service_scope', $scope)
                            ->where('gare_id', $gare->id)
                            ->whereDate('operation_date', $operationDate)
                            ->where('account_type', 'inter')
                            ->sum('amount');

                        $versementsNational = (float) VersementBancaire::query()
                            ->where('service_scope', $scope)
                            ->where('gare_id', $gare->id)
                            ->whereDate('operation_date', $operationDate)
                            ->where(function ($query) {
                                $query->where('account_type', 'national')
                                    ->orWhereNull('account_type');
                            })
                            ->sum('amount');

                        $versementsTotal = round($versementsInter + $versementsNational, 2);
                    }

                    $expectedTotal = (float) $metrics['expected_total'];
                    $difference = round($versementsTotal - $expectedTotal, 2);
                    $differenceInter = round($versementsInter - (float) $metrics['expected_inter'], 2);
                    $differenceNational = round($versementsNational - (float) $metrics['expected_national'], 2);

                    $existing = VerificationCheck::query()
                        ->where('service_scope', $scope)
                        ->where('gare_id', $gare->id)
                        ->whereDate('operation_date', $operationDate)
                        ->first();

                    $status = (abs($difference) < 0.01 && abs($differenceInter) < 0.01 && abs($differenceNational) < 0.01)
                        ? 'conforme'
                        : ($existing?->status ?: 'ecart_detecte');
                    if ($status === 'conforme' && (abs($difference) >= 0.01 || abs($differenceInter) >= 0.01 || abs($differenceNational) >= 0.01)) {
                        $status = 'ecart_detecte';
                    }

                    return VerificationCheck::updateOrCreate(
                        [
                            'service_scope' => $scope,
                            'gare_id' => $gare->id,
                            'operation_date' => $operationDate,
                        ],
                        [
                            'recettes_total' => $metrics['recette_total'],
                            'recettes_inter_total' => $metrics['recette_inter'],
                            'recettes_national_total' => $metrics['recette_national'],
                            'depenses_total' => $metrics['depense_total'],
                            'depenses_inter_total' => $metrics['depense_inter'],
                            'depenses_national_total' => $metrics['depense_national'],
                            'versements_total' => $versementsTotal,
                            'versements_inter_total' => $versementsInter,
                            'versements_national_total' => $versementsNational,
                            'expected_versement' => $metrics['expected_total'],
                            'expected_inter_versement' => $metrics['expected_inter'],
                            'expected_national_versement' => $metrics['expected_national'],
                            'difference' => $difference,
                            'difference_inter' => $differenceInter,
                            'difference_national' => $differenceNational,
                            'status' => $status,
                            'control_mode' => $controlMode,
                        ]
                    )->load('gare', 'reviewer');
                });

            $virtualCashierChecks = $this->buildVirtualCashierChecksForDate($scope, $operationDate);

            $scopeChecks = $physicalChecks->merge($virtualCashierChecks);
            $this->notifySupervisors($scopeChecks, $module);
            $checks = $checks->merge($scopeChecks);
        }

        return $checks;
    }

    public function ensureFreshForDate(?string $operationDate = null, ?string $serviceScope = null): Collection
    {
        $date = $operationDate ?: now('Africa/Abidjan')->subDay()->toDateString();

        return $this->runForDate($date, $serviceScope);
    }

    public function confirmDifference(VerificationCheck $check, User $user, ?string $note = null): VerificationCheck
    {
        $before = $check->only(['status', 'review_note', 'reviewed_by', 'reviewed_at']);

        $check->update([
            'status' => 'difference_confirmee',
            'review_note' => $note,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->activity->log($user, 'verification_confirmed', $check, 'Difference de verification confirmee.', [
            'before' => $before,
            'after' => $check->fresh()->only(['status', 'review_note', 'reviewed_by', 'reviewed_at']),
            'gare_id' => $check->gare_id,
        ]);

        return $check->refresh();
    }

    public function enableAdjustments(VerificationCheck $check, User $user, ?string $note = null): VerificationCheck
    {
        $before = $check->only(['status', 'review_note', 'reviewed_by', 'reviewed_at', 'modifications_enabled_until']);
        $until = now()->addHours(24);

        $check->update([
            'status' => 'ajustement_ouvert',
            'review_note' => $note,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'modifications_enabled_until' => $until,
        ]);

        foreach ([Recette::class, Depense::class, VersementBancaire::class] as $modelClass) {
            $modelClass::query()
                ->where('service_scope', $check->service_scope ?? 'gares')
                ->where('gare_id', $check->gare_id)
                ->whereDate('operation_date', $check->operation_date)
                ->update([
                    'force_unlocked_until' => $until,
                    'unlock_reason' => $note ?: 'Ajustement autorise depuis le module Verification',
                    'unlocked_by' => $user->id,
                ]);
        }

        $this->notifyOperatorsForAdjustment($check, $until, $note);

        $this->activity->log($user, 'verification_adjustment_opened', $check, 'Ouverture d un ajustement depuis le module Verification.', [
            'before' => $before,
            'after' => $check->fresh()->only(['status', 'review_note', 'reviewed_by', 'reviewed_at', 'modifications_enabled_until']),
            'gare_id' => $check->gare_id,
        ]);

        return $check->refresh();
    }

    protected function buildVirtualCashierChecksForDate(string $scope, string $operationDate): Collection
    {
        $cashierIds = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->where('versement_mode', 'cashier')
            ->whereNotNull('cashier_user_id')
            ->pluck('cashier_user_id')
            ->unique()
            ->values();

        return $cashierIds->map(function (int $cashierId) use ($scope, $operationDate) {
            $cashier = User::query()->find($cashierId);
            if (! $cashier || ! $cashier->canActAsCashierForScope($scope)) {
                return null;
            }

            $virtualGare = $this->virtualGares->ensureForScope($cashier, $scope);
            $metrics = $this->cashierFlow->cashierExpectedForVirtualGare($cashier, $virtualGare, $scope, $operationDate);

            $difference = round($metrics['versement_total'] - $metrics['expected_total'], 2);
            $differenceInter = round($metrics['versement_inter'] - $metrics['expected_inter'], 2);
            $differenceNational = round($metrics['versement_national'] - $metrics['expected_national'], 2);

            $existing = VerificationCheck::query()
                ->where('service_scope', $scope)
                ->where('gare_id', $virtualGare->id)
                ->whereDate('operation_date', $operationDate)
                ->first();

            $status = (abs($difference) < 0.01 && abs($differenceInter) < 0.01 && abs($differenceNational) < 0.01)
                ? 'conforme'
                : ($existing?->status ?: 'ecart_detecte');
            if ($status === 'conforme' && (abs($difference) >= 0.01 || abs($differenceInter) >= 0.01 || abs($differenceNational) >= 0.01)) {
                $status = 'ecart_detecte';
            }

            return VerificationCheck::updateOrCreate(
                [
                    'service_scope' => $scope,
                    'gare_id' => $virtualGare->id,
                    'operation_date' => $operationDate,
                ],
                [
                    'recettes_total' => $metrics['recette_total'],
                    'recettes_inter_total' => $metrics['recette_inter'],
                    'recettes_national_total' => $metrics['recette_national'],
                    'depenses_total' => $metrics['depense_total'],
                    'depenses_inter_total' => $metrics['depense_inter'],
                    'depenses_national_total' => $metrics['depense_national'],
                    'versements_total' => $metrics['versement_total'],
                    'versements_inter_total' => $metrics['versement_inter'],
                    'versements_national_total' => $metrics['versement_national'],
                    'expected_versement' => $metrics['expected_total'],
                    'expected_inter_versement' => $metrics['expected_inter'],
                    'expected_national_versement' => $metrics['expected_national'],
                    'difference' => $difference,
                    'difference_inter' => $differenceInter,
                    'difference_national' => $differenceNational,
                    'status' => $status,
                    'control_mode' => 'cashier_virtual',
                ]
            )->load('gare', 'reviewer');
        })->filter()->values();
    }

    protected function notifySupervisors(Collection $checks, ServiceModule $module): void
    {
        $anomalies = $checks->filter(function (VerificationCheck $check) {
            return abs((float) $check->difference) >= 0.01
                || abs((float) $check->difference_inter) >= 0.01
                || abs((float) $check->difference_national) >= 0.01;
        });

        if ($anomalies->isEmpty()) {
            return;
        }

        $users = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Responsable->value])
            ->where('is_active', true)
            ->get();

        foreach ($anomalies as $check) {
            foreach ($users as $user) {
                NotificationHistory::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => 'verification_mismatch',
                        'source_key' => 'verification-check:'.$check->id.':'.$user->id,
                    ],
                    [
                        'subject' => 'Ecart detecte sur la verification',
                        'content' => sprintf(
                            '[%s] %s presente un ecart total de %s FCFA pour le %s (Inter: %s, National: %s).',
                            $module->shortLabel(),
                            $check->gare?->name ?? 'Gare',
                            number_format(abs((float) $check->difference), 0, ',', ' '),
                            $check->operation_date?->format('d/m/Y'),
                            number_format(abs((float) $check->difference_inter), 0, ',', ' '),
                            number_format(abs((float) $check->difference_national), 0, ',', ' ')
                        ),
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'concerned_date' => $check->operation_date?->toDateString(),
                        'gares' => [$check->gare?->name],
                        'operations' => ['verification', $module->value],
                        'payload' => [
                            'verification_check_id' => $check->id,
                            'difference' => (float) $check->difference,
                            'difference_inter' => (float) $check->difference_inter,
                            'difference_national' => (float) $check->difference_national,
                            'module' => $module->value,
                        ],
                    ]
                );
            }
        }
    }

    protected function notifyOperatorsForAdjustment(VerificationCheck $check, \DateTimeInterface $until, ?string $note = null): void
    {
        $gare = $check->gare ?? Gare::find($check->gare_id);
        if (! $gare) {
            return;
        }

        $scope = $check->service_scope ?? 'gares';
        $roles = $scope === 'courrier'
            ? [UserRole::AgentCourrierGare->value, UserRole::CaissierCourrier->value]
            : [UserRole::ChefDeGare->value, UserRole::CaissierGare->value, UserRole::Caissiere->value, UserRole::ChefDeZone->value];

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($gare, $roles, $scope) {
                $singleRole = $scope === 'courrier' ? UserRole::AgentCourrierGare->value : UserRole::ChefDeGare->value;

                $query->where(function ($inner) use ($gare, $singleRole) {
                    $inner->where('role', $singleRole)->where('gare_id', $gare->id);
                })->orWhere(function ($inner) use ($gare, $roles) {
                    $inner->whereIn('role', $roles)
                        ->whereHas('gares', fn ($q) => $q->where('gares.id', $gare->id));
                });
            })
            ->get();

        foreach ($users as $user) {
            NotificationHistory::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'verification_adjustment_opened',
                    'source_key' => 'verification-adjustment:'.$check->id.':'.$user->id,
                ],
                [
                    'subject' => 'Ajustement autorise suite a une verification',
                    'content' => sprintf(
                        'Un ajustement est autorise pour %s (%s) jusqu au %s afin de corriger un ecart total de %s FCFA.%s',
                        $gare->name,
                        $scope === 'courrier' ? 'Service courrier' : 'Gestion des gares',
                        $until->format('d/m/Y H:i'),
                        number_format(abs((float) $check->difference), 0, ',', ' '),
                        $note ? ' Motif : '.$note : ''
                    ),
                    'status' => 'generated',
                    'control_date' => now('Africa/Abidjan')->toDateString(),
                    'concerned_date' => $check->operation_date?->toDateString(),
                    'gares' => [$gare->name],
                    'operations' => ['verification', 'ajustement', $scope],
                    'payload' => [
                        'verification_check_id' => $check->id,
                        'difference' => (float) $check->difference,
                        'difference_inter' => (float) $check->difference_inter,
                        'difference_national' => (float) $check->difference_national,
                        'enabled_until' => $until->format(DATE_ATOM),
                    ],
                ]
            );
        }
    }
}
