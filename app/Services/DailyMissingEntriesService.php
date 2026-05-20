<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CashierReceiptConfirmation;
use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\User;
use App\Models\VersementBancaire;
use Illuminate\Support\Collection;

class DailyMissingEntriesService
{
    public function __construct(
        protected CashierFlowService $cashierFlow,
        protected BankRoutingService $bankRouting
    ) {
    }

    public function rowsForDate(
        string $scope,
        string $operationDate,
        User $viewer,
        ?string $periodStartDate = null,
        ?string $periodEndDate = null
    ): Collection
    {
        $controls = DailyControl::query()
            ->with('gare.assignedCashier:id,name')
            ->where('service_scope', $scope)
            ->whereDate('concerned_date', $operationDate)
            ->whereHas('gare', function ($query) {
                $query->where('is_active', true)
                    ->where(function ($gareQuery) {
                        $gareQuery->where('is_virtual', false)
                            ->orWhere(function ($virtualQuery) {
                                $virtualQuery->where('is_virtual', true)
                                    ->whereNotNull('virtual_owner_user_id');
                            });
                    });
            })
            ->when(! $viewer->canViewAllGares($scope), fn ($query) => $query->whereIn('gare_id', $viewer->accessibleGareIds($scope)))
            ->orderBy('gare_id')
            ->get();

        if ($controls->isEmpty()) {
            return collect();
        }

        $gareIds = $controls->pluck('gare_id')->unique()->values();

        $versements = VersementBancaire::query()
            ->selectRaw('gare_id, account_type, SUM(amount) as total_amount')
            ->where('service_scope', $scope)
            ->whereDate('operation_date', $operationDate)
            ->whereIn('gare_id', $gareIds)
            ->groupBy('gare_id', 'account_type')
            ->get()
            ->groupBy('gare_id');

        $phones = $this->phonesByGare($scope, $gareIds->all());
        $coverageByVirtualGare = $this->cashierCoverageByVirtualGare(
            $controls,
            $scope,
            $operationDate,
            $periodStartDate,
            $periodEndDate
        );

        return $controls
            ->map(function (DailyControl $control) use ($scope, $operationDate, $versements, $phones, $coverageByVirtualGare) {
                $gare = $control->gare;
                if (! $gare) {
                    return null;
                }

                $missing = collect($control->missing_operations ?? [])->values();
                $isCashierManaged = ! $gare->is_virtual && ($gare->versement_mode ?? 'direct') === 'cashier';
                $recetteMissing = $missing->contains('recette');
                $depenseMissing = $missing->contains('depense');
                $validationCashierMissing = $missing->contains('validation_caissier');
                $supportsInterVersement = ! $gare->isNationalOnly();
                $supportsNationalVersement = ! $gare->isInterOnly();

                $versementCorisMissing = false;
                $versementEcobankMissing = false;

                if (! $isCashierManaged) {
                    $expected = $this->cashierFlow->expectedForGareDate($gare, $scope, $operationDate);
                    $expectedByAccount = $this->bankRouting->expectedByAccount(
                        $scope,
                        $operationDate,
                        (float) ($expected['expected_inter'] ?? 0),
                        (float) ($expected['expected_national'] ?? 0),
                        (int) $gare->id
                    );

                    $versementRows = collect($versements->get($gare->id, []));
                    $actualInter = (float) $versementRows
                        ->where('account_type', 'inter')
                        ->sum(fn ($row) => (float) ($row->total_amount ?? 0));
                    $actualNational = (float) $versementRows
                        ->filter(fn ($row) => ($row->account_type ?? 'national') !== 'inter')
                        ->sum(fn ($row) => (float) ($row->total_amount ?? 0));

                    $expectedInter = (float) ($expectedByAccount['expected_inter'] ?? 0);
                    $expectedNational = (float) ($expectedByAccount['expected_national'] ?? 0);

                    $versementEcobankMissing = $supportsInterVersement && ($expectedInter > 0.01) && ($actualInter <= 0.01);
                    $versementCorisMissing = $supportsNationalVersement && ($expectedNational > 0.01) && ($actualNational <= 0.01);

                    if ($missing->contains('versement_bancaire')) {
                        if (! $versementEcobankMissing && ! $versementCorisMissing) {
                            if (($expectedByAccount['forced_account_type'] ?? null) === 'inter') {
                                $versementEcobankMissing = $supportsInterVersement;
                            } elseif (($expectedByAccount['forced_account_type'] ?? null) === 'national') {
                                $versementCorisMissing = $supportsNationalVersement;
                            } else {
                                $versementEcobankMissing = $supportsInterVersement;
                                $versementCorisMissing = $supportsNationalVersement;
                            }
                        }
                    }
                }

                $hasMissing = $recetteMissing || $depenseMissing || $versementCorisMissing || $versementEcobankMissing || $validationCashierMissing;
                if (! $hasMissing) {
                    return null;
                }

                $cashierCoverage = $coverageByVirtualGare[$gare->id] ?? null;
                $isCashierVirtualGare = (bool) ($cashierCoverage && ($gare->is_virtual ?? false));

                return [
                    'gare' => $gare->name,
                    'operation_date' => $operationDate,
                    'recette_missing' => $recetteMissing,
                    'depense_missing' => $depenseMissing,
                    'versement_coris_missing' => $isCashierManaged ? null : $versementCorisMissing,
                    'versement_ecobank_missing' => $isCashierManaged ? null : $versementEcobankMissing,
                    'versement_coris_applicable' => $isCashierManaged ? false : $supportsNationalVersement,
                    'versement_ecobank_applicable' => $isCashierManaged ? false : $supportsInterVersement,
                    'cashier_name' => $isCashierManaged ? ($gare->assignedCashier?->name ?: '-') : null,
                    'is_cashier_virtual_gare' => $isCashierVirtualGare,
                    'cashier_coverage' => $cashierCoverage,
                    'phone' => $phones[$gare->id] ?? '-',
                    'is_cashier_managed' => $isCashierManaged,
                ];
            })
            ->filter()
            ->values();
    }

    protected function cashierCoverageByVirtualGare(
        Collection $controls,
        string $scope,
        string $operationDate,
        ?string $periodStartDate = null,
        ?string $periodEndDate = null
    ): array
    {
        $virtualGares = $controls
            ->map(fn (DailyControl $control) => $control->gare)
            ->filter(fn (?Gare $gare) => $gare && $gare->is_virtual && (int) ($gare->virtual_owner_user_id ?? 0) > 0)
            ->values();

        if ($virtualGares->isEmpty()) {
            return [];
        }

        $cashierIds = $virtualGares
            ->pluck('virtual_owner_user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($cashierIds->isEmpty()) {
            return [];
        }

        $assignedGares = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->where('versement_mode', 'cashier')
            ->whereIn('cashier_user_id', $cashierIds->all())
            ->orderBy('name')
            ->get(['id', 'name', 'cashier_user_id']);

        $assignedByCashier = $assignedGares
            ->groupBy('cashier_user_id')
            ->map(fn (Collection $gares) => $gares->values());

        $assignedGareIds = $assignedGares->pluck('id')->all();
        if ($assignedGareIds === []) {
            return $virtualGares->mapWithKeys(fn (Gare $gare) => [(int) $gare->id => [
                'validated_count' => 0,
                'expected_count' => 0,
                'rows' => [],
            ]])->all();
        }

        $periodStart = $periodStartDate ?: $operationDate;
        $periodEnd = $periodEndDate ?: $operationDate;
        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        $reportedGareIds = Recette::query()
            ->where('service_scope', $scope)
            ->whereBetween('operation_date', [$periodStart, $periodEnd])
            ->whereIn('gare_id', $assignedGareIds)
            ->pluck('gare_id')
            ->merge(
                Depense::query()
                    ->where('service_scope', $scope)
                    ->whereBetween('operation_date', [$periodStart, $periodEnd])
                    ->whereIn('gare_id', $assignedGareIds)
                    ->pluck('gare_id')
            )
            ->unique()
            ->values()
            ->all();

        $reportedLookup = array_fill_keys($reportedGareIds, true);

        $validatedLookup = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->whereBetween('operation_date', [$periodStart, $periodEnd])
            ->where('is_verified', true)
            ->whereIn('cashier_id', $cashierIds->all())
            ->whereIn('gare_id', $assignedGareIds)
            ->get(['cashier_id', 'gare_id'])
            ->groupBy('cashier_id')
            ->map(fn (Collection $rows) => array_fill_keys($rows->pluck('gare_id')->all(), true));

        return $virtualGares->mapWithKeys(function (Gare $virtualGare) use ($assignedByCashier, $reportedLookup, $validatedLookup) {
            $cashierId = (int) ($virtualGare->virtual_owner_user_id ?? 0);
            $gares = $assignedByCashier->get($cashierId, collect());
            $validatedForCashier = $validatedLookup->get($cashierId, []);

            $rows = $gares->map(function (Gare $gare) use ($reportedLookup, $validatedForCashier) {
                $isReported = isset($reportedLookup[$gare->id]);
                $isValidated = isset($validatedForCashier[$gare->id]);
                $status = $isValidated ? 'Valide' : ($isReported ? 'En attente de validation' : 'Pas enregistre');

                return [
                    'gare_name' => $gare->name,
                    'status' => $status,
                    'validated' => $isValidated,
                ];
            })->values();

            return [(int) $virtualGare->id => [
                'validated_count' => $rows->where('validated', true)->count(),
                'expected_count' => $rows->count(),
                'rows' => $rows->all(),
            ]];
        })->all();
    }

    protected function phonesByGare(string $scope, array $gareIds): array
    {
        if ($gareIds === []) {
            return [];
        }

        $primaryRole = $scope === 'courrier'
            ? UserRole::AgentCourrierGare->value
            : UserRole::ChefDeGare->value;

        $phones = User::query()
            ->selectRaw('gare_id, MIN(phone) as phone')
            ->whereIn('gare_id', $gareIds)
            ->where('role', $primaryRole)
            ->whereNotNull('phone')
            ->groupBy('gare_id')
            ->pluck('phone', 'gare_id')
            ->all();

        $missingIds = collect($gareIds)->reject(fn ($id) => isset($phones[$id]))->values();
        if ($missingIds->isEmpty()) {
            return $phones;
        }

        $fallback = Gare::query()
            ->with('assignedCashier:id,phone')
            ->whereIn('id', $missingIds->all())
            ->get()
            ->mapWithKeys(fn (Gare $gare) => [$gare->id => $gare->assignedCashier?->phone ?: '-'])
            ->all();

        return array_replace($fallback, $phones);
    }
}
