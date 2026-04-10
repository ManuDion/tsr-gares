<?php

namespace App\Http\Controllers;

use App\Exports\DailyControlsExport;
use App\Exports\DailyOperationsExport;
use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Recette;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(protected AccessScopeService $access) {}

    public function recettes(Request $request): BinaryFileResponse
    {
        $query = Recette::query()->with('gare');
        $this->access->scopeForUser($query, $request->user());

        return Excel::download(
            new DailyOperationsExport($query, 'recettes', $request->date('start_date'), $request->date('end_date')),
            'recettes_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function depenses(Request $request): BinaryFileResponse
    {
        $query = Depense::query()->with('gare');
        $this->access->scopeForUser($query, $request->user());

        return Excel::download(
            new DailyOperationsExport($query, 'depenses', $request->date('start_date'), $request->date('end_date')),
            'depenses_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function controls(Request $request): BinaryFileResponse
    {
        $query = DailyControl::query()->with('gare');

        if (! $request->user()->canViewAllGares()) {
            $query->whereIn('gare_id', $request->user()->accessibleGareIds());
        }

        return Excel::download(
            new DailyControlsExport($query, $request->date('start_date'), $request->date('end_date')),
            'controles_journaliers_'.now()->format('Ymd_His').'.xlsx'
        );
    }
}
