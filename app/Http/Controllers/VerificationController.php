<?php

namespace App\Http\Controllers;

use App\Models\VerificationCheck;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(protected VerificationService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VerificationCheck::class);

        $operationDate = $request->date('operation_date')
            ? $request->date('operation_date')->toDateString()
            : now('Africa/Abidjan')->subDay()->toDateString();

        $this->service->ensureFreshForDate($operationDate);

        $checks = VerificationCheck::query()
            ->with(['gare', 'reviewer'])
            ->whereDate('operation_date', $operationDate)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('difference')
            ->orderBy('gare_id')
            ->paginate(20)
            ->withQueryString();

        return view('verifications.index', [
            'checks' => $checks,
            'operationDate' => $operationDate,
            'statuses' => [
                'conforme' => 'Conforme',
                'ecart_detecte' => 'Écart détecté',
                'difference_confirmee' => 'Différence confirmée',
                'ajustement_ouvert' => 'Ajustement ouvert',
            ],
        ]);
    }

    public function confirm(Request $request, VerificationCheck $verification): RedirectResponse
    {
        $this->authorize('update', $verification);

        $this->service->confirmDifference(
            $verification,
            $request->user(),
            $request->string('review_note')->toString() ?: null
        );

        return back()->with('status', 'La différence a été confirmée.');
    }

    public function enableAdjustments(Request $request, VerificationCheck $verification): RedirectResponse
    {
        $this->authorize('update', $verification);

        $this->service->enableAdjustments(
            $verification,
            $request->user(),
            $request->string('review_note')->toString() ?: null
        );

        return back()->with('status', 'Les ajustements ont été ouverts pour la gare concernée.');
    }

    public function purgePeriod(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $deleted = VerificationCheck::query()
            ->whereBetween('operation_date', [$data['start_date'], $data['end_date']])
            ->delete();

        return back()->with('status', "{$deleted} vérification(s) supprimée(s).");
    }
}
