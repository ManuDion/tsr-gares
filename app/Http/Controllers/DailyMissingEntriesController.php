<?php

namespace App\Http\Controllers;

use App\Services\DailyControlService;
use App\Services\DailyMissingEntriesService;
use App\Support\ModuleContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DailyMissingEntriesController extends Controller
{
    public function __construct(
        protected DailyControlService $dailyControlService,
        protected DailyMissingEntriesService $missingEntries
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAccessFinancialScope($module->financialScope()), 403);

        $scope = ModuleContext::financialScope($module);
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $this->ensureControlsFreshForRange($scope, $startDate, $endDate);
        $rows = $this->rowsForPeriod($scope, $startDate, $endDate, $user);

        return view('verifications.missing-entries', [
            'module' => $module,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $this->periodLabel($startDate, $endDate),
            'rows' => $rows,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $user = $request->user();
        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAccessFinancialScope($module->financialScope()), 403);

        $scope = ModuleContext::financialScope($module);
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $this->ensureControlsFreshForRange($scope, $startDate, $endDate);
        $rows = $this->rowsForPeriod($scope, $startDate, $endDate, $user);

        $html = view('verifications.missing-entries-pdf', [
            'module' => $module,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $this->periodLabel($startDate, $endDate),
            'rows' => $rows,
        ])->render();

        if (! class_exists(\Dompdf\Dompdf::class)) {
            abort(500, 'Export PDF indisponible: le moteur Dompdf n est pas installe.');
        }

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="fiche-ecritures-manquantes-'.$scope.'-'.$startDate.'-au-'.$endDate.'.pdf"',
        ]);
    }

    protected function resolveDateRange(Request $request): array
    {
        $legacyOperationDate = $request->date('operation_date');
        $start = $request->date('start_date');
        $end = $request->date('end_date');

        if (! $start && ! $end && $legacyOperationDate) {
            $start = $legacyOperationDate;
            $end = $legacyOperationDate;
        }

        if (! $start && ! $end) {
            $today = now('Africa/Abidjan')->toDateString();

            return [$today, $today];
        }

        $startDate = ($start ?: $end)->toDateString();
        $endDate = ($end ?: $start)->toDateString();

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    protected function ensureControlsFreshForRange(string $scope, string $startDate, string $endDate): void
    {
        foreach (new \DatePeriod(
            new \DateTimeImmutable($startDate),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($endDate))->modify('+1 day')
        ) as $date) {
            $this->dailyControlService->ensureFreshControl($date->format('Y-m-d'), $scope);
        }
    }

    protected function rowsForPeriod(string $scope, string $startDate, string $endDate, User $viewer): Collection
    {
        $rows = collect();

        foreach (new \DatePeriod(
            new \DateTimeImmutable($startDate),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($endDate))->modify('+1 day')
        ) as $date) {
            $dateRows = $this->missingEntries->rowsForDate(
                $scope,
                $date->format('Y-m-d'),
                $viewer,
                $startDate,
                $endDate
            );

            $rows = $rows->merge($dateRows);
        }

        return $rows
            ->sortBy([
                fn (array $row) => (string) ($row['operation_date'] ?? ''),
                fn (array $row) => mb_strtolower((string) ($row['gare'] ?? '')),
            ])
            ->values();
    }

    protected function periodLabel(string $startDate, string $endDate): string
    {
        if ($startDate === $endDate) {
            return \Carbon\Carbon::parse($startDate)->format('d/m/Y');
        }

        return \Carbon\Carbon::parse($startDate)->format('d/m/Y').' au '.\Carbon\Carbon::parse($endDate)->format('d/m/Y');
    }
}
