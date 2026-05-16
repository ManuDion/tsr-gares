<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DailyControl;
use App\Models\Gare;
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

    public function rowsForDate(string $scope, string $operationDate, User $viewer): Collection
    {
        $controls = DailyControl::query()
            ->with('gare')
            ->where('service_scope', $scope)
            ->whereDate('concerned_date', $operationDate)
            ->whereHas('gare', fn ($query) => $query->where('is_virtual', false))
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

        return $controls
            ->map(function (DailyControl $control) use ($scope, $operationDate, $versements, $phones) {
                $gare = $control->gare;
                if (! $gare) {
                    return null;
                }

                $missing = collect($control->missing_operations ?? [])->values();
                $isCashierManaged = ! $gare->is_virtual && ($gare->versement_mode ?? 'direct') === 'cashier';
                $recetteMissing = $missing->contains('recette');
                $depenseMissing = $missing->contains('depense');
                $validationCashierMissing = $missing->contains('validation_caissier');

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

                    $versementEcobankMissing = ((float) ($expectedByAccount['expected_inter'] ?? 0) > 0.01) && ($actualInter <= 0.01);
                    $versementCorisMissing = ((float) ($expectedByAccount['expected_national'] ?? 0) > 0.01) && ($actualNational <= 0.01);

                    if ($missing->contains('versement_bancaire')) {
                        if (! $versementEcobankMissing && ! $versementCorisMissing) {
                            if (($expectedByAccount['forced_account_type'] ?? null) === 'inter') {
                                $versementEcobankMissing = true;
                            } elseif (($expectedByAccount['forced_account_type'] ?? null) === 'national') {
                                $versementCorisMissing = true;
                            } else {
                                $versementEcobankMissing = true;
                                $versementCorisMissing = true;
                            }
                        }
                    }
                }

                $hasMissing = $recetteMissing || $depenseMissing || $versementCorisMissing || $versementEcobankMissing || $validationCashierMissing;
                if (! $hasMissing) {
                    return null;
                }

                return [
                    'gare' => $gare->name,
                    'recette_missing' => $recetteMissing,
                    'depense_missing' => $depenseMissing,
                    'versement_coris_missing' => $isCashierManaged ? ($validationCashierMissing ? true : null) : $versementCorisMissing,
                    'versement_ecobank_missing' => $isCashierManaged ? ($validationCashierMissing ? true : null) : $versementEcobankMissing,
                    'phone' => $phones[$gare->id] ?? '-',
                    'is_cashier_managed' => $isCashierManaged,
                ];
            })
            ->filter()
            ->values();
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
