<?php

namespace App\Http\Controllers;

use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Support\ModuleContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($module->supportsFinancialFlows(), 403);
        $scope = $module->financialScope() ?? 'gares';

        $startDate = $request->date('start_date')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $endDate = $request->date('end_date')?->toDateString() ?? now()->toDateString();

        $topSaisie = Gare::query()
            ->withCount([
                'recettes as recettes_count' => fn ($q) => $q->where('service_scope', $scope)->whereBetween('operation_date', [$startDate, $endDate]),
                'depenses as depenses_count' => fn ($q) => $q->where('service_scope', $scope)->whereBetween('operation_date', [$startDate, $endDate]),
                'versementsBancaires as versements_count' => fn ($q) => $q->where('service_scope', $scope)->whereBetween('operation_date', [$startDate, $endDate]),
            ])
            ->get()
            ->map(function ($gare) {
                $gare->saisie_total = $gare->recettes_count + $gare->depenses_count + $gare->versements_count;
                return $gare;
            })
            ->sortByDesc('saisie_total')
            ->take(5)
            ->values();

        $topRecettes = Recette::query()
            ->selectRaw('gare_id, SUM(amount) as total_amount')
            ->where('service_scope', $scope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->groupBy('gare_id')
            ->with('gare')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        $topDepenses = Depense::query()
            ->selectRaw('gare_id, SUM(amount) as total_amount')
            ->where('service_scope', $scope)
            ->whereBetween('operation_date', [$startDate, $endDate])
            ->groupBy('gare_id')
            ->with('gare')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        return view('reports.performance', compact('topSaisie', 'topRecettes', 'topDepenses', 'startDate', 'endDate', 'module'));
    }
}
