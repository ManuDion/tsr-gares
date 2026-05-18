<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreCashierReceiptConfirmationRequest;
use App\Models\CashierReceiptConfirmation;
use App\Models\Gare;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\CashierValidationNotificationService;
use App\Services\CashierFlowService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierReceiptController extends Controller
{
    public function __construct(
        protected CashierFlowService $flow,
        protected ActivityLogService $activity,
        protected CashierValidationNotificationService $validationNotifications
    )
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
        $phones = $this->phonesByGare($scope, $gares->pluck('id')->all());
        $confirmations = CashierReceiptConfirmation::query()
            ->where('service_scope', $scope)
            ->where('cashier_id', $user->id)
            ->whereDate('operation_date', $operationDate)
            ->get()
            ->keyBy('gare_id');

        $rows = $gares->map(function (Gare $gare) use ($user, $scope, $operationDate, $confirmations, $phones) {
            $expected = $this->flow->expectedForGareDate($gare, $scope, $operationDate, $user);
            $confirmation = $confirmations->get($gare->id);
            $hasOperations = (float) ($expected['recette_total'] ?? 0) > 0.01
                || (float) ($expected['depense_total'] ?? 0) > 0.01;

            if ($confirmation?->is_verified) {
                return null;
            }

            if (! $confirmation && ! $hasOperations) {
                return null;
            }

            return [
                'gare' => $gare,
                'expected' => $expected,
                'confirmation' => $confirmation,
                'phone' => $phones[$gare->id] ?? '-',
                'is_locked' => $this->flow->isGareDateLocked($gare, $scope, $operationDate),
            ];
        })->filter()->values();

        return view('cashier-receipts.index', [
            'module' => $module,
            'operationDate' => $operationDate,
            'rows' => $rows,
            'collectsInter' => $user->cashierCollectsInter(),
            'collectsNational' => $user->cashierCollectsNational(),
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
        $operationDate = $request->date('operation_date')->toDateString();
        $payload = $request->validated();
        $mode = (string) ($payload['mode'] ?? 'validate');

        if ($mode === 'unlock') {
            if (! $this->flow->isGareDateLocked($gare, $scope, $operationDate)) {
                return redirect()->route('cashier-receipts.index', [
                    'module' => $module->value,
                    'operation_date' => $operationDate,
                ])->with('status', 'La ligne n est pas verrouillee.');
            }

            $duration = (int) ($payload['unlock_duration'] ?? 0);
            $unit = (string) ($payload['unlock_unit'] ?? 'hours');
            $unitLabel = match ($unit) {
                'minutes' => 'minute(s)',
                'days' => 'jour(s)',
                default => 'heure(s)',
            };
            $until = $this->flow->unlockGareDateOperations(
                $user,
                $gare,
                $scope,
                $operationDate,
                $duration,
                $unit
            );
            $this->activity->log($user, 'cashier_operations_unlocked', $gare, 'Déverrouillage caissier des opérations d une gare.', [
                'gare_id' => $gare->id,
                'before' => [
                    'service_scope' => $scope,
                    'operation_date' => $operationDate,
                    'is_locked' => true,
                ],
                'after' => [
                    'service_scope' => $scope,
                    'operation_date' => $operationDate,
                    'unlocked_until' => $until->format('Y-m-d H:i:s'),
                    'unlock_duration' => $duration,
                    'unlock_unit' => $unit,
                ],
                'extra' => [
                    'module' => $module->value,
                ],
            ]);

            return redirect()->route('cashier-receipts.index', [
                'module' => $module->value,
                'operation_date' => $operationDate,
            ])->with('status', "Déverrouillage actif pour {$duration} {$unitLabel} (jusqu au {$until->format('d/m/Y H:i')}).");
        }

        $payload['is_verified'] = true;

        $this->flow->upsertConfirmation(
            $user,
            $gare,
            $scope,
            $operationDate,
            $payload
        );

        $this->validationNotifications->notifyStationManagerForValidation(
            $scope,
            $gare,
            $operationDate,
            $user
        );

        return redirect()->route('cashier-receipts.index', [
            'module' => $module->value,
            'operation_date' => $operationDate,
        ])->with('status', 'Validation caissier enregistree.');
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
