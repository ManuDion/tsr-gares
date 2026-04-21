<?php

namespace App\Http\Controllers;

use App\Models\NotificationHistory;
use App\Services\DailyControlService;
use App\Services\DocumentExpiryService;
use App\Services\VerificationService;
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

        $query = NotificationHistory::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->when($user->isControleur(), fn ($builder) => $builder->whereIn('type', [
                'document_expired',
                'document_expiry_daily',
                'document_expiry_weekly',
            ]))
            ->orderByDesc('concerned_date')
            ->orderByDesc('created_at');

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function purgePeriod(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $deleted = NotificationHistory::query()
            ->whereBetween('created_at', [
                now()->parse($data['start_date'])->startOfDay(),
                now()->parse($data['end_date'])->endOfDay(),
            ])
            ->delete();

        return back()->with('status', "{$deleted} historique(s) de notification supprimé(s).");
    }
}
