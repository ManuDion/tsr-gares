<?php

namespace App\Http\Controllers;

use App\Models\NotificationHistory;
use App\Services\DailyControlService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationHistoryController extends Controller
{
    public function __construct(protected DailyControlService $dailyControlService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', NotificationHistory::class);
        $this->dailyControlService->ensureFreshControl();

        $user = $request->user();

        $query = NotificationHistory::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->orderByDesc('concerned_date')
            ->orderByDesc('created_at');

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
