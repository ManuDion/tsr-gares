<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashierReceiptConfirmationRequest;
use App\Models\CashierReceiptConfirmation;
use App\Models\Gare;
use App\Services\CashierFlowService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierReceiptController extends Controller
{
    public function __construct(protected CashierFlowService $flow)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);

        $scope = ModuleContext::financialScope($module);
        abort_unless($user->canActAsCashierForScope($scope), 403);

        $operationDate = $request->date('operation_date')
            ? $request->date('operation_date')->toDateString()
            : now('Africa/Abidjan')->toDateString();

        $gares = $this->flow->garesForCashier($user, $scope);
        $confirmations = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $user->id)
            ->whereDate('operation_date', $operationDate)
            ->get()
            ->keyBy('gare_id');

        $rows = $gares->map(function (Gare $gare) use ($scope, $operationDate, $confirmations) {
            $expected = $this->flow->expectedForGareDate($gare, $scope, $operationDate);
            $confirmation = $confirmations->get($gare->id);

            return [
                'gare' => $gare,
                'expected' => $expected,
                'confirmation' => $confirmation,
            ];
        })->filter(fn (array $row) => ! ($row['confirmation']?->is_verified ?? false))
            ->values();

        return view('cashier-receipts.index', [
            'module' => $module,
            'operationDate' => $operationDate,
            'rows' => $rows,
        ]);
    }

    public function store(StoreCashierReceiptConfirmationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $module = ModuleContext::fromRequest($request, $user);
        $scope = ModuleContext::financialScope($module);
        abort_unless($user->canActAsCashierForScope($scope), 403);

        $gare = Gare::query()->findOrFail($request->integer('gare_id'));
        abort_unless((int) $gare->cashier_user_id === (int) $user->id, 403);

        $this->flow->upsertConfirmation(
            $user,
            $gare,
            $scope,
            $request->date('operation_date')->toDateString(),
            $request->validated()
        );

        return redirect()->route('cashier-receipts.index', [
            'module' => $module->value,
            'operation_date' => $request->date('operation_date')->toDateString(),
        ])->with('status', 'Validation caissier enregistree.');
    }
}
