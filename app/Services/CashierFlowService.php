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

    public function expectedForGareDate(Gare $gare, string $scope, string $operationDate): array
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
        $recetteTotal = round($recetteInter + $recetteNational, 2);

        if ($recetteTotal <= 0) {
            $depenseInter = 0.0;
            $depenseNational = round($depensesTotal, 2);
        } else {
            $depenseInter = round($depensesTotal * ($recetteInter / $recetteTotal), 2);
            $depenseNational = round($depensesTotal - $depenseInter, 2);
        }

        $expectedInter = round($recetteInter - $depenseInter, 2);
        $expectedNational = round($recetteNational - $depenseNational, 2);
        $expectedTotal = round($expectedInter + $expectedNational, 2);

        return [
            'recette_total' => $recetteTotal,
            'recette_inter' => $recetteInter,
            'recette_national' => $recetteNational,
            'depense_total' => round($depensesTotal, 2),
            'depense_inter' => $depenseInter,
            'depense_national' => $depenseNational,
            'expected_total' => $expectedTotal,
            'expected_inter' => $expectedInter,
            'expected_national' => $expectedNational,
        ];
    }

    public function garesForCashier(User $cashier, string $scope): Collection
    {
        return Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->where('versement_mode', 'cashier')
            ->where('cashier_user_id', $cashier->id)
            ->whereIn('id', $cashier->accessibleGareIds($scope))
            ->orderBy('name')
            ->get();
    }

    public function upsertConfirmation(
        User $cashier,
        Gare $gare,
        string $scope,
        string $operationDate,
        array $payload
    ): CashierReceiptConfirmation {
        $expected = $this->expectedForGareDate($gare, $scope, $operationDate);
        $requestedVerification = (bool) ($payload['is_verified'] ?? false);

        if ($requestedVerification) {
            $receivedInter = (float) $expected['expected_inter'];
            $receivedNational = (float) $expected['expected_national'];
            $receivedTotal = (float) $expected['expected_total'];
            $verified = true;
        } else {
            $receivedInter = round((float) ($payload['received_inter_total'] ?? 0), 2);
            $receivedNational = round((float) ($payload['received_national_total'] ?? 0), 2);
            $receivedTotal = round((float) ($payload['received_total'] ?? ($receivedInter + $receivedNational)), 2);
            $verified = abs($receivedTotal - $expected['expected_total']) < 0.01;
        }

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

    public function verifiedReceiptsForCashier(User $cashier, string $scope, string $operationDate): array
    {
        $query = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $cashier->id)
            ->whereDate('operation_date', $operationDate)
            ->where('is_verified', true);

        return [
            'total' => (float) $query->clone()->sum('received_total'),
            'inter' => (float) $query->clone()->sum('received_inter_total'),
            'national' => (float) $query->clone()->sum('received_national_total'),
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

        $depenseInter = round(min($received['inter'], $depensesTotal), 2);
        $depenseNational = round(max(0, $depensesTotal - $depenseInter), 2);

        $expectedInter = round($received['inter'] - $depenseInter, 2);
        $expectedNational = round($received['national'] - $depenseNational, 2);
        $expectedTotal = round($expectedInter + $expectedNational, 2);

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
            'recette_total' => round($received['total'], 2),
            'recette_inter' => round($received['inter'], 2),
            'recette_national' => round($received['national'], 2),
            'depense_total' => round($depensesTotal, 2),
            'depense_inter' => $depenseInter,
            'depense_national' => $depenseNational,
            'expected_total' => $expectedTotal,
            'expected_inter' => $expectedInter,
            'expected_national' => $expectedNational,
            'versement_total' => round($versementInter + $versementNational, 2),
            'versement_inter' => round($versementInter, 2),
            'versement_national' => round($versementNational, 2),
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

        $inter = round((float) $confirmed->clone()->sum('received_inter_total'), 2);
        $national = round((float) $confirmed->clone()->sum('received_national_total'), 2);
        $total = round($inter + $national, 2);

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
}
