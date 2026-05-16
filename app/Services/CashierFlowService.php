<?php

namespace App\Services;

use App\Models\CashierReceiptConfirmation;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\User;
use App\Models\VersementBancaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashierFlowService
{
    public function __construct(protected CashierVirtualGareService $virtualGares)
    {
    }

    public function expectedForGareDate(Gare $gare, string $scope, string $operationDate, ?User $cashier = null): array
    {
        $recettes = Recette::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $gare->id)
            ->whereDate('operation_date', $operationDate);

        $depensesTotal = (float) Depense::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $gare->id)
            ->whereDate('operation_date', $operationDate)
            ->sum('amount');

        $recetteInter = (float) $recettes->clone()->sum(DB::raw('ticket_inter_amount + bagage_inter_amount'));
        $recetteNational = (float) $recettes->clone()->sum(DB::raw('ticket_national_amount + bagage_national_amount'));
        $recetteTotal = round($recetteInter + $recetteNational, 0);

        if ($recetteTotal <= 0) {
            $depenseInter = 0.0;
            $depenseNational = round($depensesTotal, 0);
        } else {
            $depenseInter = round($depensesTotal * ($recetteInter / $recetteTotal), 0);
            $depenseNational = round($depensesTotal - $depenseInter, 0);
        }

        $expectedInter = round($recetteInter - $depenseInter, 0);
        $expectedNational = round($recetteNational - $depenseNational, 0);
        $expectedTotal = round($expectedInter + $expectedNational, 0);

        $totals = [
            'recette_total' => $recetteTotal,
            'recette_inter' => $recetteInter,
            'recette_national' => $recetteNational,
            'depense_total' => round($depensesTotal, 0),
            'depense_inter' => $depenseInter,
            'depense_national' => $depenseNational,
            'expected_total' => $expectedTotal,
            'expected_inter' => $expectedInter,
            'expected_national' => $expectedNational,
        ];

        if (! $cashier) {
            return $totals;
        }

        return $this->filterByCashierCollectionMode($totals, $cashier);
    }

    public function garesForCashier(User $cashier, string $scope): Collection
    {
        $query = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->where('versement_mode', 'cashier')
            ->where('cashier_user_id', $cashier->id)
            ->whereIn('id', $cashier->accessibleGareIds($scope))
            ->orderBy('name');

        if ($scope === 'courrier') {
            $query->where(function ($inner) {
                $inner->whereNull('activity_mode')
                    ->orWhere('activity_mode', '!=', 'inter_only');
            });
        }

        return $query->get();
    }

    public function upsertConfirmation(
        User $cashier,
        Gare $gare,
        string $scope,
        string $operationDate,
        array $payload
    ): CashierReceiptConfirmation {
        $expected = $this->expectedForGareDate($gare, $scope, $operationDate, $cashier);
        $requestedVerification = (bool) ($payload['is_verified'] ?? false);
        $receivedInter = $cashier->cashierCollectsInter()
            ? (int) round((float) ($payload['received_inter_total'] ?? 0), 0)
            : 0;
        $receivedNational = $cashier->cashierCollectsNational()
            ? (int) round((float) ($payload['received_national_total'] ?? 0), 0)
            : 0;
        $receivedTotal = (int) round((float) ($payload['received_total'] ?? ($receivedInter + $receivedNational)), 0);
        $verified = $requestedVerification;

        $confirmation = CashierReceiptConfirmation::query()->updateOrCreate(
            [
                'service_scope' => $scope,
                'gare_id' => $gare->id,
                'cashier_id' => $cashier->id,
                'operation_date' => $operationDate,
            ],
            [
                'expected_total' => $expected['expected_total'],
                'expected_inter_total' => $expected['expected_inter'],
                'expected_national_total' => $expected['expected_national'],
                'received_total' => $receivedTotal,
                'received_inter_total' => $receivedInter,
                'received_national_total' => $receivedNational,
                'is_verified' => $verified,
                'verified_at' => $verified ? now() : null,
                'verified_by' => $verified ? $cashier->id : null,
                'note' => $payload['note'] ?? null,
            ]
        );

        $this->syncVirtualRecetteFromConfirmedGares($cashier, $scope, $operationDate);

        return $confirmation->refresh();
    }

    protected function filterByCashierCollectionMode(array $totals, User $cashier): array
    {
        if (! $cashier->cashierCollectsInter()) {
            $totals['recette_inter'] = 0;
            $totals['depense_inter'] = 0;
            $totals['expected_inter'] = 0;
        }

        if (! $cashier->cashierCollectsNational()) {
            $totals['recette_national'] = 0;
            $totals['depense_national'] = 0;
            $totals['expected_national'] = 0;
        }

        $totals['recette_total'] = round((float) $totals['recette_inter'] + (float) $totals['recette_national'], 0);
        $totals['depense_total'] = round((float) $totals['depense_inter'] + (float) $totals['depense_national'], 0);
        $totals['expected_total'] = round((float) $totals['expected_inter'] + (float) $totals['expected_national'], 0);

        return $totals;
    }

    public function pendingValidationsCount(User $cashier, string $scope, ?string $operationDate = null): int
    {
        $operationDate ??= now('Africa/Abidjan')->toDateString();
        $gareIds = $this->garesForCashier($cashier, $scope)->pluck('id')->all();
        if ($gareIds === []) {
            return 0;
        }

        $recetteIds = Recette::query()
            ->where('service_scope', $scope)
            ->whereDate('operation_date', $operationDate)
            ->whereIn('gare_id', $gareIds)
            ->pluck('gare_id');

        $depenseIds = Depense::query()
            ->where('service_scope', $scope)
            ->whereDate('operation_date', $operationDate)
            ->whereIn('gare_id', $gareIds)
            ->pluck('gare_id');

        $activeGareIds = $recetteIds
            ->merge($depenseIds)
            ->unique()
            ->values();

        if ($activeGareIds->isEmpty()) {
            return 0;
        }

        $verifiedGareIds = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $cashier->id)
            ->whereDate('operation_date', $operationDate)
            ->where('is_verified', true)
            ->whereIn('gare_id', $activeGareIds->all())
            ->pluck('gare_id');

        return $activeGareIds->diff($verifiedGareIds)->count();
    }

    public function verifiedReceiptsForCashier(User $cashier, string $scope, string $operationDate): array
    {
        $query = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $cashier->id)
            ->whereDate('operation_date', $operationDate)
            ->where('is_verified', true);

        return [
            'total' => round((float) $query->clone()->sum('received_total'), 0),
            'inter' => round((float) $query->clone()->sum('received_inter_total'), 0),
            'national' => round((float) $query->clone()->sum('received_national_total'), 0),
        ];
    }

    public function cashierExpectedForVirtualGare(User $cashier, Gare $virtualGare, string $scope, string $operationDate): array
    {
        $received = $this->verifiedReceiptsForCashier($cashier, $scope, $operationDate);

        $depensesTotal = (float) Depense::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $virtualGare->id)
            ->whereDate('operation_date', $operationDate)
            ->sum('amount');

        $depenseInter = round(min($received['inter'], $depensesTotal), 0);
        $depenseNational = round(max(0, $depensesTotal - $depenseInter), 0);

        $expectedInter = round($received['inter'] - $depenseInter, 0);
        $expectedNational = round($received['national'] - $depenseNational, 0);
        $expectedTotal = round($expectedInter + $expectedNational, 0);

        $versementInter = (float) VersementBancaire::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $virtualGare->id)
            ->whereDate('operation_date', $operationDate)
            ->where('account_type', 'inter')
            ->sum('amount');

        $versementNational = (float) VersementBancaire::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $virtualGare->id)
            ->whereDate('operation_date', $operationDate)
            ->where(function ($query) {
                $query->where('account_type', 'national')
                    ->orWhereNull('account_type');
            })
            ->sum('amount');

        return [
            'recette_total' => round($received['total'], 0),
            'recette_inter' => round($received['inter'], 0),
            'recette_national' => round($received['national'], 0),
            'depense_total' => round($depensesTotal, 0),
            'depense_inter' => $depenseInter,
            'depense_national' => $depenseNational,
            'expected_total' => $expectedTotal,
            'expected_inter' => $expectedInter,
            'expected_national' => $expectedNational,
            'versement_total' => round($versementInter + $versementNational, 0),
            'versement_inter' => round($versementInter, 0),
            'versement_national' => round($versementNational, 0),
        ];
    }

    public function syncVirtualRecetteFromConfirmedGares(User $cashier, string $scope, string $operationDate): void
    {
        $virtualGare = $this->virtualGares->ensureForScope($cashier, $scope);

        $confirmed = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $cashier->id)
            ->whereDate('operation_date', $operationDate)
            ->where('is_verified', true);

        $inter = round((float) $confirmed->clone()->sum('received_inter_total'), 0);
        $national = round((float) $confirmed->clone()->sum('received_national_total'), 0);
        $total = round($inter + $national, 0);

        $existing = Recette::query()
            ->where('service_scope', $scope)
            ->where('gare_id', $virtualGare->id)
            ->whereDate('operation_date', $operationDate)
            ->first();

        if ($total <= 0) {
            if ($existing) {
                $existing->delete();
            }

            return;
        }

        Recette::query()->updateOrCreate(
            [
                'service_scope' => $scope,
                'gare_id' => $virtualGare->id,
                'operation_date' => $operationDate,
            ],
            [
                'ticket_inter_amount' => $inter,
                'ticket_national_amount' => $national,
                'bagage_inter_amount' => 0,
                'bagage_national_amount' => 0,
                'amount' => $total,
                'description' => 'Recette automatique caissier (somme des gares verifiees).',
                'reference' => null,
                'created_by' => $existing?->created_by ?? $cashier->id,
                'updated_by' => $cashier->id,
            ]
        );
    }

    public function isGareDateLocked(Gare $gare, string $scope, string $operationDate): bool
    {
        $records = Recette::query()
            ->select(['id', 'created_at', 'force_unlocked_until'])
            ->where('service_scope', $scope)
            ->where('gare_id', $gare->id)
            ->whereDate('operation_date', $operationDate)
            ->get()
            ->concat(
                Depense::query()
                    ->select(['id', 'created_at', 'force_unlocked_until'])
                    ->where('service_scope', $scope)
                    ->where('gare_id', $gare->id)
                    ->whereDate('operation_date', $operationDate)
                    ->get()
            )
            ->concat(
                VersementBancaire::query()
                    ->select(['id', 'created_at', 'force_unlocked_until'])
                    ->where('service_scope', $scope)
                    ->where('gare_id', $gare->id)
                    ->whereDate('operation_date', $operationDate)
                    ->get()
            );

        if ($records->isEmpty()) {
            return false;
        }

        $threshold = now()->subHours(48);

        return $records->every(function ($record) use ($threshold) {
            $isWithinBaseWindow = $record->created_at?->greaterThanOrEqualTo($threshold) ?? false;
            $hasActiveUnlock = $record->force_unlocked_until && $record->force_unlocked_until->isFuture();

            return ! $isWithinBaseWindow && ! $hasActiveUnlock;
        });
    }

    public function unlockGareDateOperations(
        User $cashier,
        Gare $gare,
        string $scope,
        string $operationDate,
        int $duration,
        string $unit,
        ?string $reason = null
    ): \DateTimeInterface {
        $until = match ($unit) {
            'minutes' => now()->addMinutes($duration),
            'days' => now()->addDays($duration),
            default => now()->addHours($duration),
        };

        $payload = [
            'force_unlocked_until' => $until,
            'unlock_reason' => $reason ?: 'Deverrouillage caissier depuis Validation des sommes recues',
            'unlocked_by' => $cashier->id,
        ];

        foreach ([Recette::class, Depense::class, VersementBancaire::class] as $modelClass) {
            $modelClass::query()
                ->where('service_scope', $scope)
                ->where('gare_id', $gare->id)
                ->whereDate('operation_date', $operationDate)
                ->update($payload);
        }

        return $until;
    }
}
