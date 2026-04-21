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
    public function __construct(protected ActivityLogService $activity)
    {
    }

    public function runForDate(string $operationDate, ?string $serviceScope = null): Collection
    {
        $scopes = $serviceScope ? [$serviceScope] : ['gares', 'courrier'];
        $checks = collect();

        foreach ($scopes as $scope) {
            $module = $scope === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

            $scopeChecks = Gare::query()
                ->where('is_active', true)
                ->get()
                ->map(function (Gare $gare) use ($operationDate, $scope) {
                    $recettes = (float) Recette::query()
                        ->where('service_scope', $scope)
                        ->where('gare_id', $gare->id)
                        ->whereDate('operation_date', $operationDate)
                        ->sum('amount');

                    $depenses = (float) Depense::query()
                        ->where('service_scope', $scope)
                        ->where('gare_id', $gare->id)
                        ->whereDate('operation_date', $operationDate)
                        ->sum('amount');

                    $versements = (float) VersementBancaire::query()
                        ->where('service_scope', $scope)
                        ->where('gare_id', $gare->id)
                        ->whereDate('operation_date', $operationDate)
                        ->sum('amount');

                    $expected = round($recettes - $depenses, 2);
                    $difference = round($versements - $expected, 2);

                    $existing = VerificationCheck::query()
                        ->where('service_scope', $scope)
                        ->where('gare_id', $gare->id)
                        ->whereDate('operation_date', $operationDate)
                        ->first();

                    $status = abs($difference) < 0.01 ? 'conforme' : ($existing?->status ?: 'ecart_detecte');
                    if ($status === 'conforme' && abs($difference) >= 0.01) {
                        $status = 'ecart_detecte';
                    }

                    return VerificationCheck::updateOrCreate(
                        [
                            'service_scope' => $scope,
                            'gare_id' => $gare->id,
                            'operation_date' => $operationDate,
                        ],
                        [
                            'recettes_total' => $recettes,
                            'depenses_total' => $depenses,
                            'versements_total' => $versements,
                            'expected_versement' => $expected,
                            'difference' => $difference,
                            'status' => $status,
                        ]
                    )->load('gare', 'reviewer');
                });

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

        $this->activity->log($user, 'verification_confirmed', $check, 'Différence de vérification confirmée.', [
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
                    'unlock_reason' => $note ?: 'Ajustement autorisé depuis le module Vérification',
                    'unlocked_by' => $user->id,
                ]);
        }

        $this->notifyOperatorsForAdjustment($check, $until, $note);

        $this->activity->log($user, 'verification_adjustment_opened', $check, 'Ouverture d’un ajustement depuis le module Vérification.', [
            'before' => $before,
            'after' => $check->fresh()->only(['status', 'review_note', 'reviewed_by', 'reviewed_at', 'modifications_enabled_until']),
            'gare_id' => $check->gare_id,
        ]);

        return $check->refresh();
    }

    protected function notifySupervisors(Collection $checks, ServiceModule $module): void
    {
        $anomalies = $checks->filter(fn (VerificationCheck $check) => abs((float) $check->difference) >= 0.01);

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
                        'subject' => 'Écart détecté sur la vérification',
                        'content' => sprintf(
                            '[%s] %s présente un écart de %s FCFA pour le %s. Versements : %s FCFA | Recettes - Dépenses : %s FCFA.',
                            $module->shortLabel(),
                            $check->gare?->name ?? 'Gare',
                            number_format(abs((float) $check->difference), 0, ',', ' '),
                            $check->operation_date?->format('d/m/Y'),
                            number_format((float) $check->versements_total, 0, ',', ' '),
                            number_format((float) $check->expected_versement, 0, ',', ' ')
                        ),
                        'status' => 'generated',
                        'control_date' => now('Africa/Abidjan')->toDateString(),
                        'concerned_date' => $check->operation_date?->toDateString(),
                        'gares' => [$check->gare?->name],
                        'operations' => ['verification', $module->value],
                        'payload' => [
                            'verification_check_id' => $check->id,
                            'difference' => (float) $check->difference,
                            'expected_versement' => (float) $check->expected_versement,
                            'versements_total' => (float) $check->versements_total,
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
                    'subject' => 'Ajustement autorisé suite à une vérification',
                    'content' => sprintf(
                        'Un ajustement est autorisé pour %s (%s) jusqu’au %s afin de corriger un écart de %s FCFA.%s',
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
                        'enabled_until' => $until->format(DATE_ATOM),
                    ],
                ]
            );
        }
    }
}
