<?php

namespace App\Http\Controllers;

use App\Enums\ServiceModule;
use App\Services\DailyControlService;
use App\Services\DocumentExpiryService;
use App\Services\VerificationService;
use App\Support\ModuleContext;
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
        $module = ModuleContext::fromRequest($request, $request->user());

        if ($module->supportsFinancialFlows()) {
            $scope = ModuleContext::financialScope($module);
            $this->dailyControlService->ensureFreshControl(null, $scope);
            $this->verificationService->ensureFreshForDate(null, $scope);
        }

        if ($module === ServiceModule::Documents || $request->user()->isControleur() || $request->user()->hasGlobalVisibility()) {
            $this->documentExpiryService->ensureFreshAlerts();
        }

        [$heading, $subheading] = match ($module) {
            ServiceModule::Gares => ['Dashboard — Gestion des gares', 'Pilotage opérationnel et financier du service de gestion des gares.'],
            ServiceModule::Documents => ['Dashboard — Gestion des documents', 'Suivi documentaire, échéances réglementaires et conformité.'],
            ServiceModule::Courrier => ['Dashboard — Service courrier', 'Pilotage du service courrier par gare et par caissier courrier.'],
            ServiceModule::Rh => ['Dashboard — Ressources Humaines', 'Vue d’ensemble du personnel, des dossiers et des comptes à activer.'],
        };

        return view('dashboard.index', compact('heading', 'subheading', 'module'));
    }
}
