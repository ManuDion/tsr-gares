<?php

namespace App\Http\Controllers;

use App\Enums\ServiceModule;
use App\Models\Gare;
use App\Models\VerificationCheck;
use App\Services\VerificationService;
use App\Support\ModuleContext;
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

        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($module->supportsFinancialFlows(), 403);
        $serviceScope = ModuleContext::financialScope($module);

        $operationDate = $request->date('operation_date')
            ? $request->date('operation_date')->toDateString()
            : now('Africa/Abidjan')->subDay()->toDateString();

        $this->service->ensureFreshForDate($operationDate, $serviceScope);

        $checks = VerificationCheck::query()
            ->with(['gare', 'reviewer'])
            ->where('service_scope', $serviceScope)
            ->whereDate('operation_date', $operationDate)
            ->whereHas('gare', fn ($q) => $q->where('is_active', true))
            ->when(! $request->user()->canViewAllGares($serviceScope), fn ($q) => $q->whereIn('gare_id', $request->user()->accessibleGareIds($serviceScope)))
            ->when($request->filled('gare_id'), fn ($q) => $q->where('gare_id', (int) $request->integer('gare_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('difference')
            ->orderBy('gare_id')
            ->paginate(20)
            ->withQueryString();

        $gares = Gare::query()
            ->where('is_active', true)
            ->when(
                ! $request->user()->canViewAllGares($serviceScope),
                fn ($q) => $q->whereIn('id', $request->user()->accessibleGareIds($serviceScope))
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('verifications.index', [
            'checks' => $checks,
            'gares' => $gares,
            'operationDate' => $operationDate,
            'module' => $module,
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

        $module = ($verification->service_scope ?? 'gares') === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

        return redirect()->route('verifications.index', ['module' => $module->value, 'operation_date' => optional($verification->operation_date)->toDateString()])
            ->with('status', 'La différence a été confirmée.');
    }

    public function enableAdjustments(Request $request, VerificationCheck $verification): RedirectResponse
    {
        $this->authorize('update', $verification);
        abort_unless($request->user()->canUnlockFinancialScope($verification->service_scope), 403);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
            'unlock_duration' => ['required', 'integer', 'min:1', 'max:10000'],
            'unlock_unit' => ['required', 'in:minutes,hours,days'],
        ]);

        $duration = (int) ($validated['unlock_duration'] ?? 24);
        $unit = (string) ($validated['unlock_unit'] ?? 'hours');
        $unitLabel = match ($unit) {
            'minutes' => 'minute(s)',
            'days' => 'jour(s)',
            default => 'heure(s)',
        };

        $this->service->enableAdjustments(
            $verification,
            $request->user(),
            isset($validated['review_note']) ? (trim((string) $validated['review_note']) ?: null) : null,
            $duration,
            $unit
        );

        $module = ($verification->service_scope ?? 'gares') === 'courrier' ? ServiceModule::Courrier : ServiceModule::Gares;

        return redirect()->route('verifications.index', ['module' => $module->value, 'operation_date' => optional($verification->operation_date)->toDateString()])
            ->with('status', "Les ajustements ont été ouverts pour {$duration} {$unitLabel}.");
    }

    public function purgePeriod(Request $request): RedirectResponse
    {
        $module = ModuleContext::fromRequest($request, $request->user());
        abort_unless($request->user()->canAdministerModule($module), 403);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $serviceScope = $module->supportsFinancialFlows() ? ModuleContext::financialScope($module) : 'gares';

        $deleted = VerificationCheck::query()
            ->where('service_scope', $serviceScope)
            ->whereBetween('operation_date', [$data['start_date'], $data['end_date']])
            ->delete();

        return back()->with('status', "{$deleted} vérification(s) supprimée(s).");
    }
}

