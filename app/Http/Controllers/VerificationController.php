<?php

namespace App\Http\Controllers;

use App\Models\CashierReceiptConfirmation;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Services\VerificationService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(protected VerificationService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VerificationCheck::class);
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        $serviceScope = ModuleContext::financialScope($module);
        $canUseAdminVerificationScope = $user->canAdministerModule($module) || $user->isVerificateur();
        $canPurgeVerificationPeriod = $canUseAdminVerificationScope;

        $legacyOperationDate = $request->date('operation_date');
        $start = $request->date('start_date');
        $end = $request->date('end_date');

        if (! $start && ! $end && $legacyOperationDate) {
            $start = $legacyOperationDate;
            $end = $legacyOperationDate;
        }

        if (! $start && ! $end) {
            $today = now('Africa/Abidjan')->toDateString();
            $startDate = $today;
            $endDate = $today;
        } else {
            $startDate = ($start ?: $end)->toDateString();
            $endDate = ($end ?: $start)->toDateString();
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        foreach (new \DatePeriod(
            new \DateTimeImmutable($startDate),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($endDate))->modify('+1 day')
        ) as $date) {
            $this->service->ensureFreshForDate($date->format('Y-m-d'), $serviceScope);
        }

        $checks = VerificationCheck::query()
            ->with(['gare.assignedCashier', 'reviewer'])
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->whereHas('gare', fn ($q) => $q->where('is_active', true))
            ->when(! $canUseAdminVerificationScope, fn ($q) => $q->whereIn('gare_id', $user->accessibleGareIds($serviceScope)))
            ->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', (int) $request->integer('gare_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('difference')
            ->orderBy('gare_id')
            ->paginate(20)
            ->withQueryString();

        $gares = Gare::query()
            ->where('is_active', true)
            ->when(
                ! $canUseAdminVerificationScope,
                fn ($q) => $q->whereIn('id', $user->accessibleGareIds($serviceScope))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $isSingleDayPeriod = $startDate === $endDate;
        $cashierCoverageByCheck = $isSingleDayPeriod
            ? $this->buildCashierCoverageByCheck(
                $checks->getCollection(),
                $serviceScope,
                $startDate,
                $user
            )
            : [];

        return view('verifications.index', [
            'checks' => $checks,
            'gares' => $gares,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isSingleDayPeriod' => $isSingleDayPeriod,
            'module' => $module,
            'cashierCoverageByCheck' => $cashierCoverageByCheck,
            'canPurgeVerificationPeriod' => $canPurgeVerificationPeriod,
            'statuses' => [
                'conforme' => 'Conforme',
                'ecart_detecte' => 'Écart détecté',
                'difference_confirmee' => 'Différence confirmée',
                'ajustement_ouvert' => 'Ajustement ouvert',
            ],
        ]);
    }

    public function confirm(Request $request, VerificationCheck $verification): RedirectResponse
    {
        $this->authorize('update', $verification);

        if ($verification->status === 'difference_confirmee') {
            return back()->with('status', 'Cette ligne est deja validee.');
        }

        $this->service->confirmDifference(
            $verification,
            $request->user(),
            $request->string('review_note')->toString() ?: null
        );

        return back()->with('status', 'La difference a ete confirmee.');
    }

    public function enableAdjustments(Request $request, VerificationCheck $verification): RedirectResponse
    {
        $this->authorize('update', $verification);
        abort_unless($request->user()->canSuperviseFinancialScope($verification->service_scope), 403);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
            'unlock_duration' => ['required', 'integer', 'min:1', 'max:10000'],
            'unlock_unit' => ['required', 'in:minutes,hours,days'],
        ]);

        $duration = (int) ($validated['unlock_duration'] ?? 24);
        $unit = (string) ($validated['unlock_unit'] ?? 'hours');
        $unitLabel = match ($unit) {
            'minutes' => 'minute(s)',
            'days' => 'jour(s)',
            default => 'heure(s)',
        };

        $this->service->enableAdjustments(
            $verification,
            $request->user(),
            isset($validated['review_note']) ? (trim((string) $validated['review_note']) ?: null) : null,
            $duration,
            $unit
        );

        return back()->with('status', "Les ajustements ont ete ouverts pour {$duration} {$unitLabel}.");
    }

    public function purgePeriod(Request $request): RedirectResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($request->user()->canAdministerModule($module) || $request->user()->isVerificateur(), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $serviceScope = $module->supportsFinancialFlows() ? ModuleContext::financialScope($module) : 'gares';

        $deleted = VerificationCheck::query()
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$data['start_date'], $data['end_date']])
            ->delete();

        return back()->with('status', "{$deleted} vérification(s) supprimée(s).");
    }

    protected function buildCashierCoverageByCheck(
        Collection $checks,
        string $scope,
        string $operationDate,
        User $viewer
    ): array {
        $cashierByCheck = $checks
            ->filter(function (VerificationCheck $check) {
                return (bool) ($check->gare?->is_virtual ?? false)
                    && (int) ($check->gare?->virtual_owner_user_id ?? 0) > 0;
            })
            ->mapWithKeys(fn (VerificationCheck $check) => [$check->id => (int) $check->gare->virtual_owner_user_id])
            ->all();

        if ($cashierByCheck === []) {
            return [];
        }

        $cashierIds = array_values(array_unique(array_values($cashierByCheck)));

        $assignedGaresQuery = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', false)
            ->where('versement_mode', 'cashier')
            ->whereIn('cashier_user_id', $cashierIds);

        if (! $viewer->canViewAllGares($scope)) {
            $assignedGaresQuery->whereIn('id', $viewer->accessibleGareIds($scope));
        }

        $assignedGares = $assignedGaresQuery
            ->orderBy('name')
            ->get(['id', 'name', 'cashier_user_id']);

        if ($assignedGares->isEmpty()) {
            return collect($cashierByCheck)->mapWithKeys(function (int $cashierId, int $checkId) {
                return [$checkId => [
                    'validated_count' => 0,
                    'expected_count' => 0,
                    'rows' => [],
                ]];
            })->all();
        }

        $assignedByCashier = $assignedGares
            ->groupBy('cashier_user_id')
            ->map(fn (Collection $gares) => $gares->values());

        $gareIds = $assignedGares->pluck('id')->all();

        $reportedGareIds = Recette::query()
            ->where('service_scope', $scope)
            ->whereDate('operation_date', $operationDate)
            ->whereIn('gare_id', $gareIds)
            ->pluck('gare_id')
            ->merge(
                Depense::query()
                    ->where('service_scope', $scope)
                    ->whereDate('operation_date', $operationDate)
                    ->whereIn('gare_id', $gareIds)
                    ->pluck('gare_id')
            )
            ->unique()
            ->values()
            ->all();

        $reportedLookup = array_fill_keys($reportedGareIds, true);

        $verifiedLookup = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->whereDate('operation_date', $operationDate)
            ->whereIn('cashier_id', $cashierIds)
            ->whereIn('gare_id', $gareIds)
            ->where('is_verified', true)
            ->get(['cashier_id', 'gare_id'])
            ->groupBy('cashier_id')
            ->map(function (Collection $rows) {
                return array_fill_keys($rows->pluck('gare_id')->all(), true);
            });

        $coverageByCashier = [];

        foreach ($cashierIds as $cashierId) {
            $gares = $assignedByCashier->get($cashierId, collect());
            $verifiedForCashier = $verifiedLookup->get($cashierId, []);

            $rows = $gares->map(function (Gare $gare) use ($reportedLookup, $verifiedForCashier) {
                $hasReported = isset($reportedLookup[$gare->id]);
                $isValidated = isset($verifiedForCashier[$gare->id]);

                return [
                    'gare_name' => $gare->name,
                    'missing' => ! $hasReported,
                    'validated' => $isValidated,
                ];
            })->values();

            $coverageByCashier[$cashierId] = [
                'validated_count' => $rows->where('validated', true)->count(),
                'expected_count' => $rows->count(),
                'rows' => $rows->all(),
            ];
        }

        return collect($cashierByCheck)->mapWithKeys(function (int $cashierId, int $checkId) use ($coverageByCashier) {
            return [$checkId => $coverageByCashier[$cashierId] ?? [
                'validated_count' => 0,
                'expected_count' => 0,
                'rows' => [],
            ]];
        })->all();
    }
}

