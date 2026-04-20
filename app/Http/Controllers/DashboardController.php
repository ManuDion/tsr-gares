<?php

namespace App\Http\Controllers;

use App\Services\DailyControlService;
use App\Services\DocumentExpiryService;
use App\Services\VerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DailyControlService $dailyControlService,
        protected VerificationService $verificationService,
        protected DocumentExpiryService $documentExpiryService,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $this->dailyControlService->ensureFreshControl();
        $this->verificationService->ensureFreshForDate();
        $this->documentExpiryService->ensureFreshAlerts();

        $user = $request->user();

        [$heading, $subheading] = match (true) {
            $user->isAdmin() => ['Dashboard global', 'Supervision globale des gares, flux financiers et anomalies'],
            $user->isResponsable() => ['Dashboard supervision', 'Vision consolidée des opérations, alertes et performances'],
            $user->isChefDeGare() => ['Dashboard gare', 'Suivi opérationnel de votre gare et régularisation des saisies'],
            $user->isControleur() => ['Dashboard conformité', 'Suivi des documents administratifs et des échéances réglementaires'],
            default => ['Dashboard caissière', 'Saisie et suivi des gares qui vous sont affectées'],
        };

        return view('dashboard.index', compact('heading', 'subheading'));
    }
}
