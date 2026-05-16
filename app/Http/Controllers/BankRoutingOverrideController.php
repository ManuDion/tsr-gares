<?php

namespace App\Http\Controllers;

use App\Models\BankRoutingOverride;
use App\Services\AccessScopeService;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BankRoutingOverrideController extends Controller
{
    public function __construct(protected AccessScopeService $access)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAdministerModule($module), 403);
        $scope = ModuleContext::financialScope($module);

        $active = BankRoutingOverride::query()
            ->with('gares:id,name')
            ->where('service_scope', $scope)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $history = BankRoutingOverride::query()
            ->with(['creator', 'updater', 'gares:id,name'])
            ->where('service_scope', $scope)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('verifications.bank-routing-overrides', [
            'module' => $module,
            'scope' => $scope,
            'activeOverrides' => $active,
            'history' => $history,
            'gares' => $this->access->availableGares($user, $scope),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAdministerModule($module), 403);
        $data = $request->validate(
            [
                'forced_account_type' => ['required', 'in:inter,national'],
                'start_date' => ['required', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'notes' => ['nullable', 'string', 'max:255'],
                'gare_ids' => ['nullable', 'array'],
                'gare_ids.*' => ['integer', 'distinct', 'exists:gares,id'],
            ]
        );
        $requestedGareIds = collect($data['gare_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $targetScopes = $user->hasGlobalVisibility()
            ? ['gares', 'courrier']
            : [ModuleContext::financialScope($module)];

        $createdScopes = [];
        foreach ($targetScopes as $targetScope) {
            $allowedGareIds = $this->resolveAllowedGareIdsForScope($user, $targetScope, $requestedGareIds);
            if (! empty($requestedGareIds) && empty($allowedGareIds)) {
                continue;
            }

            $override = BankRoutingOverride::query()->create([
                'service_scope' => $targetScope,
                'forced_account_type' => $data['forced_account_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! empty($allowedGareIds)) {
                $override->gares()->sync($allowedGareIds);
            }

            $createdScopes[] = $targetScope;
        }

        if (empty($createdScopes)) {
            throw ValidationException::withMessages([
                'gare_ids' => "Aucune gare selectionnee n'est eligible pour le(s) service(s) cible(s).",
            ]);
        }

        return redirect()
            ->route('bank-routing-overrides.index', ['module' => $module->value])
            ->with('status', $this->storeStatusMessage($user->hasGlobalVisibility(), $createdScopes));
    }

    public function disable(Request $request, BankRoutingOverride $override): RedirectResponse
    {
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAdministerModule($module), 403);
        $scope = ModuleContext::financialScope($module);
        abort_unless($override->service_scope === $scope, 403);

        $override->update([
            'is_active' => false,
            'updated_by' => $user->id,
        ]);

        return redirect()
            ->route('bank-routing-overrides.index', ['module' => $module->value])
            ->with('status', 'Parametrage desactive.');
    }

    public function disableAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAdministerModule($module), 403);
        $scopes = $user->hasGlobalVisibility() ? ['gares', 'courrier'] : [ModuleContext::financialScope($module)];
        $updated = BankRoutingOverride::query()
            ->whereIn('service_scope', $scopes)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('bank-routing-overrides.index', ['module' => $module->value])
            ->with('status', $updated > 0
                ? 'Retour au mode normal active. Toutes les regles actives ont ete desactivees.'
                : 'Aucune regle active a desactiver. Le mode normal est deja en place.');
    }

    public function destroy(Request $request, BankRoutingOverride $override): RedirectResponse
    {
        $user = $request->user();

        $module = ModuleContext::fromRequest($request, $user);
        abort_unless($module->supportsFinancialFlows(), 403);
        abort_unless($user->canAdministerModule($module), 403);
        $scope = ModuleContext::financialScope($module);
        abort_unless($override->service_scope === $scope, 403);

        $override->delete();

        return redirect()
            ->route('bank-routing-overrides.index', ['module' => $module->value])
            ->with('status', 'Historique supprime.');
    }

    protected function resolveAllowedGareIdsForScope($user, string $scope, array $requestedGareIds): array
    {
        if (empty($requestedGareIds)) {
            return [];
        }

        $availableIds = $this->access->availableGares($user, $scope)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($requestedGareIds, $availableIds));
    }

    protected function storeStatusMessage(bool $isGlobalVisibility, array $createdScopes): string
    {
        if (! $isGlobalVisibility) {
            return 'Parametrage de banque enregistre pour votre service.';
        }

        if (count($createdScopes) === 2) {
            return 'Parametrage de banque enregistre pour les deux services (gares et courrier).';
        }

        if ($createdScopes === ['gares']) {
            return 'Parametrage de banque enregistre uniquement pour le service gares.';
        }

        if ($createdScopes === ['courrier']) {
            return 'Parametrage de banque enregistre uniquement pour le service courrier.';
        }

        return 'Parametrage de banque enregistre.';
    }
}
