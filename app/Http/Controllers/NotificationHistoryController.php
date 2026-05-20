<?php

namespace App\Http\Controllers;

use App\Models\Gare;
use App\Models\NotificationHistory;
use App\Services\DailyControlService;
use App\Services\DocumentExpiryService;
use App\Services\VerificationService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationHistoryController extends Controller
{
    public function __construct(
        protected DailyControlService $dailyControlService,
        protected VerificationService $verificationService,
        protected DocumentExpiryService $documentExpiryService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', NotificationHistory::class);

        $user = $request->user();
        $module = ModuleContext::fromRequest($request, $user);

        $query = NotificationHistory::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->forModule($module)
            ->orderByDesc('concerned_date')
            ->orderByDesc('created_at');

        if ($module->supportsFinancialFlows()) {
            $scope = ModuleContext::financialScope($module);
            $activeGares = Gare::query()
                ->where('is_active', true)
                ->when(! $user->canViewAllGares($scope), fn ($q) => $q->whereIn('id', $user->accessibleGareIds($scope)))
                ->get(['id', 'name']);
            $activeGareIds = $activeGares->pluck('id')->map(fn ($id) => (int) $id)->all();
            $activeGareNames = $activeGares->pluck('name')->filter()->values()->all();

            if ($activeGareIds !== [] || $activeGareNames !== []) {
                $query->where(function ($builder) use ($activeGareIds, $activeGareNames) {
                    if ($activeGareIds !== []) {
                        $builder->whereIn('payload->gare_id', $activeGareIds);
                    }

                    $builder->orWhere(function ($inner) use ($activeGareNames) {
                        $inner->whereNull('payload->gare_id')
                            ->where(function ($garesFilter) use ($activeGareNames) {
                                $garesFilter->whereNull('gares')
                                    ->orWhereJsonLength('gares', 0);

                                foreach ($activeGareNames as $gareName) {
                                    $garesFilter->orWhereJsonContains('gares', $gareName);
                                }
                            });
                    });
                });
            } else {
                $query->where(function ($builder) {
                    $builder->whereNull('gares')
                        ->orWhereJsonLength('gares', 0);
                });
            }
        }

        $period = $request->string('period')->toString();
        if (
            $period === ''
            && ! $request->filled('start_date')
            && ! $request->filled('end_date')
        ) {
            $period = 'today';
        }

        if ($period === 'today') {
            $query->whereDate('created_at', now('Africa/Abidjan')->toDateString());
        } else {
            $query->when($request->filled('start_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('start_date')))
                ->when($request->filled('end_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('end_date')));
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'module' => $module,
            'period' => $period,
        ]);
    }

    public function purgePeriod(Request $request): RedirectResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($request->user()->canAdministerModule($module), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $deleted = NotificationHistory::query()
            ->forModule($module)
            ->whereBetween('created_at', [
                now()->parse($data['start_date'])->startOfDay(),
                now()->parse($data['end_date'])->endOfDay(),
            ])
            ->delete();

        return back()->with('status', "{$deleted} historique(s) de notification supprimé(s).");
    }
}

