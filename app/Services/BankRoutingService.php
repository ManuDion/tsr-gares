<?php

namespace App\Services;

use App\Models\BankRoutingOverride;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BankRoutingService
{
    public function activeOverrideForDate(string $scope, CarbonInterface|string $date, ?int $gareId = null): ?BankRoutingOverride
    {
        $dateValue = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $exact = $this->applyGareCondition(
            BankRoutingOverride::query()
            ->with('gares:id')
            ->where('service_scope', $scope)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $dateValue)
            ->where(function ($query) use ($dateValue) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $dateValue);
            })
        , $gareId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($exact) {
            return $exact;
        }

        return $this->applyGareCondition(
            BankRoutingOverride::query()
            ->with('gares:id')
            ->whereIn('service_scope', ['gares', 'courrier'])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $dateValue)
            ->where(function ($query) use ($dateValue) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $dateValue);
            })
        , $gareId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function forcedAccountTypeForDate(string $scope, CarbonInterface|string $date, ?int $gareId = null): ?string
    {
        return $this->activeOverrideForDate($scope, $date, $gareId)?->forced_account_type;
    }

    public function enforceAccountType(string $scope, CarbonInterface|string $date, ?string $requestedAccountType, ?int $gareId = null): string
    {
        $forced = $this->forcedAccountTypeForDate($scope, $date, $gareId);
        if ($forced) {
            return $forced;
        }

        return $requestedAccountType === 'inter' ? 'inter' : 'national';
    }

    public function expectedByAccount(string $scope, CarbonInterface|string $date, float $expectedInter, float $expectedNational, ?int $gareId = null): array
    {
        $forced = $this->forcedAccountTypeForDate($scope, $date, $gareId);
        $expectedTotal = round($expectedInter + $expectedNational, 0);

        if ($forced === 'inter') {
            return [
                'expected_inter' => $expectedTotal,
                'expected_national' => 0.0,
                'expected_total' => $expectedTotal,
                'forced_account_type' => 'inter',
            ];
        }

        if ($forced === 'national') {
            return [
                'expected_inter' => 0.0,
                'expected_national' => $expectedTotal,
                'expected_total' => $expectedTotal,
                'forced_account_type' => 'national',
            ];
        }

        return [
            'expected_inter' => round($expectedInter, 0),
            'expected_national' => round($expectedNational, 0),
            'expected_total' => $expectedTotal,
            'forced_account_type' => null,
        ];
    }

    public function activeWindowsForScope(string $scope): Collection
    {
        return BankRoutingOverride::query()
            ->with('gares:id,name')
            ->where('service_scope', $scope)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'service_scope', 'forced_account_type', 'start_date', 'end_date']);
    }

    protected function applyGareCondition(Builder $query, ?int $gareId): Builder
    {
        return $query->where(function (Builder $scopeQuery) use ($gareId) {
            $scopeQuery->whereDoesntHave('gares');

            if (! $gareId) {
                return;
            }

            $scopeQuery->orWhereHas('gares', function (Builder $gareQuery) use ($gareId) {
                $gareQuery->where('gares.id', $gareId);
            });
        });
    }
}
