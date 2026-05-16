<?php

namespace App\Http\Controllers;

use App\Services\DailyControlService;
use App\Services\DailyMissingEntriesService;
use App\Support\ModuleContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($request->user()->canSuperviseFinancialScope($module->financialScope()), 403);

        $scope = ModuleContext::financialScope($module);
        $operationDate = $request->date('operation_date')
            ? $request->date('operation_date')->toDateString()
            : now('Africa/Abidjan')->subDay()->toDateString();

        $this->dailyControlService->ensureFreshControl($operationDate, $scope);

        $rows = $this->missingEntries->rowsForDate($scope, $operationDate, $request->user());

        return view('verifications.missing-entries', [
            'module' => $module,
            'operationDate' => $operationDate,
            'rows' => $rows,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($request->user()->canSuperviseFinancialScope($module->financialScope()), 403);

        $scope = ModuleContext::financialScope($module);
        $operationDate = $request->date('operation_date')
            ? $request->date('operation_date')->toDateString()
            : now('Africa/Abidjan')->subDay()->toDateString();

        $this->dailyControlService->ensureFreshControl($operationDate, $scope);
        $rows = $this->missingEntries->rowsForDate($scope, $operationDate, $request->user());

        $html = view('verifications.missing-entries-pdf', [
            'module' => $module,
            'operationDate' => $operationDate,
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
            'Content-Disposition' => 'attachment; filename="fiche-ecritures-manquantes-'.$scope.'-'.$operationDate.'.pdf"',
        ]);
    }
}
