<?php

namespace App\Http\Controllers;

use App\Exports\DailyControlsExport;
use App\Exports\DailyOperationsExport;
use App\Models\DailyControl;
use App\Models\Depense;
use App\Models\Gare;
use App\Models\Recette;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Support\ModuleContext;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(protected AccessScopeService $access) {}

    public function recettes(Request $request): BinaryFileResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        $scope = $module->financialScope() ?? 'gares';

        $query = Recette::query()->with('gare')->financiallyValidated();
        $this->access->scopeForUser($query, $request->user(), 'gare_id', $scope);
        $this->restrictToCashierVirtualGares($query, $request->user(), $scope);

        return Excel::download(
            new DailyOperationsExport($query, 'recettes', $request->date('start_date'), $request->date('end_date')),
            'recettes_'.$scope.'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function depenses(Request $request): BinaryFileResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        $scope = $module->financialScope() ?? 'gares';

        $query = Depense::query()->with('gare')->financiallyValidated();
        $this->access->scopeForUser($query, $request->user(), 'gare_id', $scope);
        $this->restrictToCashierVirtualGares($query, $request->user(), $scope);

        return Excel::download(
            new DailyOperationsExport($query, 'depenses', $request->date('start_date'), $request->date('end_date')),
            'depenses_'.$scope.'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function controls(Request $request): BinaryFileResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        $scope = $module->financialScope() ?? 'gares';

        $query = DailyControl::query()->with('gare')->where('service_scope', $scope);

        if (! $request->user()->canViewAllGares($scope)) {
            $query->whereIn('gare_id', $request->user()->accessibleGareIds($scope));
        }

        return Excel::download(
            new DailyControlsExport($query, $request->date('start_date'), $request->date('end_date')),
            'controles_'.$scope.'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    protected function restrictToCashierVirtualGares($query, User $user, string $scope): void
    {
        if (! $user->canActAsCashierForScope($scope) || $user->canActAsChefForScope($scope)) {
            return;
        }

        $virtualGareIds = Gare::query()
            ->where('is_active', true)
            ->where('is_virtual', true)
            ->where('virtual_owner_user_id', $user->id)
            ->where('virtual_scope', $scope)
            ->pluck('id')
            ->all();

        $query->whereIn('gare_id', $virtualGareIds);
    }
}
