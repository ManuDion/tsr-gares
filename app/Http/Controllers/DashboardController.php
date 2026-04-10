<?php

namespace App\Http\Controllers;

use App\Services\DailyControlService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DailyControlService $dailyControlService)
    {
    }

    public function __invoke(Request $request): View
    {
        $this->dailyControlService->ensureFreshControl();

        $user = $request->user();

        [$heading, $subheading] = match (true) {
            $user->isAdmin() => ['Dashboard global', 'Supervision globale des gares, flux financiers et anomalies'],
            $user->isResponsable() => ['Dashboard supervision', 'Vision consolidée des opérations, alertes et performances'],
            $user->isChefDeGare() => ['Dashboard gare', 'Suivi opérationnel de votre gare et régularisation des saisies'],
            default => ['Dashboard caissière', 'Saisie et suivi des gares qui vous sont affectées'],
        };

        return view('dashboard.index', compact('heading', 'subheading'));
    }
}
