<?php

namespace App\Http\Controllers;

use App\Enums\ServiceModule;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ModuleContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);
        $module = ModuleContext::fromRequest($request, $request->user());

        $query = ActivityLog::query()
            ->with(['user', 'gare'])
            ->whereNotNull('before')
            ->whereNotNull('after')
            ->latest('id');
        $this->applyModuleFilter($query, $module);

        $query->when($request->filled('search'), function ($builder) use ($request) {
            $search = $request->string('search')->toString();

            $builder->where(function ($inner) use ($search) {
                $inner->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('entity_type', 'like', '%'.$search.'%')
                    ->orWhere('event_type', 'like', '%'.$search.'%');
            });
        });

        $query->when($request->filled('user_id'), fn ($builder) => $builder->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event_type'), fn ($builder) => $builder->where('event_type', $request->string('event_type')))
            ->when($request->filled('entity_type'), fn ($builder) => $builder->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('start_date'), fn ($builder) => $builder->whereDate('created_at', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($builder) => $builder->whereDate('created_at', '<=', $request->date('end_date')));

        $eventLabels = [
            'gare_updated' => 'Gare modifiée',
            'gare_toggled' => 'Statut de gare modifié',
            'user_updated' => 'Utilisateur modifié',
            'user_toggled' => 'Statut utilisateur modifié',
            'recette_updated' => 'Recette modifiée',
            'recette_unlocked' => 'Recette déverrouillée',
            'recette_attachment_added' => 'Justificatif de recette ajouté',
            'depense_updated' => 'Dépense modifiée',
            'depense_attachment_added' => 'Justificatif de dépense ajouté',
            'depense_unlocked' => 'Dépense déverrouillée',
            'versement_updated' => 'Versement modifié',
            'versement_unlocked' => 'Versement déverrouillé',
            'versement_attachment_added' => 'Bordereau ajouté',
            'versement_analysis_success' => 'Lecture OCR réussie',
            'versement_analysis_failed' => 'Échec de lecture OCR',
            'verification_confirmed' => 'Écart confirmé',
            'verification_adjustment_opened' => 'Ajustement ouvert',
            'administrative_document_created' => 'Document administratif créé',
            'administrative_document_updated' => 'Document administratif mis à jour',
            'administrative_document_deleted' => 'Document administratif supprimé',
        ];

        $filterSeed = ActivityLog::query()
            ->whereNotNull('before')
            ->whereNotNull('after');
        $this->applyModuleFilter($filterSeed, $module);

        return view('activity-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => (clone $filterSeed)->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
            'entityTypes' => (clone $filterSeed)->select('entity_type')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'eventLabels' => $eventLabels,
            'module' => $module,
        ]);
    }

    public function destroy(Request $request, ActivityLog $activityLog): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $activityLog->delete();

        return redirect()->route('activity-logs.index', $request->except('page'))->with('status', 'Historique supprimé.');
    }

    public function show(Request $request, ActivityLog $activityLog): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);
        $module = ModuleContext::fromRequest($request, $request->user());

        $isAllowed = ActivityLog::query()
            ->whereKey($activityLog->id)
            ->tap(fn ($query) => $this->applyModuleFilter($query, $module))
            ->exists();
        abort_unless($isAllowed, 404);

        $eventLabels = [
            'gare_updated' => 'Gare modifiée',
            'gare_toggled' => 'Statut de gare modifié',
            'user_updated' => 'Utilisateur modifié',
            'user_toggled' => 'Statut utilisateur modifié',
            'recette_updated' => 'Recette modifiée',
            'recette_unlocked' => 'Recette déverrouillée',
            'recette_attachment_added' => 'Justificatif de recette ajouté',
            'depense_updated' => 'Dépense modifiée',
            'depense_attachment_added' => 'Justificatif de dépense ajouté',
            'depense_unlocked' => 'Dépense déverrouillée',
            'versement_updated' => 'Versement modifié',
            'versement_unlocked' => 'Versement déverrouillé',
            'versement_attachment_added' => 'Bordereau ajouté',
            'versement_analysis_success' => 'Lecture OCR réussie',
            'versement_analysis_failed' => 'Échec de lecture OCR',
            'verification_confirmed' => 'Écart confirmé',
            'verification_adjustment_opened' => 'Ajustement ouvert',
            'administrative_document_created' => 'Document administratif créé',
            'administrative_document_updated' => 'Document administratif mis à jour',
            'administrative_document_deleted' => 'Document administratif supprimé',
        ];

        return view('activity-logs.show', [
            'log' => $activityLog->load(['user', 'gare']),
            'eventLabels' => $eventLabels,
            'module' => $module,
        ]);
    }

    protected function applyModuleFilter($query, ServiceModule $module): void
    {
        if ($module === ServiceModule::Documents) {
            $query->where(function ($builder) {
                $builder->whereIn('event_type', [
                    'administrative_document_created',
                    'administrative_document_updated',
                    'administrative_document_deleted',
                ])
                    ->orWhere('entity_type', 'AdministrativeDocument')
                    ->orWhere('after->module', ServiceModule::Documents->value)
                    ->orWhere('before->module', ServiceModule::Documents->value);
            });

            return;
        }

        if ($module === ServiceModule::Rh) {
            $query->where(function ($builder) {
                $builder->where('event_type', 'like', 'rh_%')
                    ->orWhereIn('entity_type', ['Employee', 'EmployeeDocument', 'EmployeeAssignment'])
                    ->orWhere('after->module', ServiceModule::Rh->value)
                    ->orWhere('before->module', ServiceModule::Rh->value);
            });

            return;
        }

        $scope = $module->financialScope() ?? 'gares';
        $financialEventMap = $this->financialEventToEntityMap();

        $query->where(function ($builder) use ($scope, $financialEventMap, $module) {
            if ($module === ServiceModule::Gares) {
                $builder->whereIn('event_type', [
                    'gare_created',
                    'gare_updated',
                    'gare_deleted',
                    'gare_toggled',
                ]);
            }

            foreach ($financialEventMap as $eventType => $table) {
                $builder->orWhere(function ($inner) use ($eventType, $table, $scope) {
                    $inner->where('event_type', $eventType)
                        ->whereExists(function ($exists) use ($table, $scope) {
                            $exists->selectRaw('1')
                                ->from($table)
                                ->whereColumn($table.'.id', 'activity_logs.entity_id')
                                ->where($table.'.service_scope', $scope);
                        });
                });
            }

            $builder->orWhere(function ($inner) use ($scope) {
                $inner->where(function ($events) {
                    $events->where('event_type', 'verification_confirmed')
                        ->orWhere('event_type', 'verification_adjustment_opened');
                })->whereExists(function ($exists) use ($scope) {
                    $exists->selectRaw('1')
                        ->from('verification_checks')
                        ->whereColumn('verification_checks.id', 'activity_logs.entity_id')
                        ->where('verification_checks.service_scope', $scope);
                });
            });

            $builder->orWhere('after->module', $module->value)
                ->orWhere('before->module', $module->value)
                ->orWhere('meta->extra->module', $module->value);
        });
    }

    protected function financialEventToEntityMap(): Collection
    {
        return collect([
            'recette_created' => 'recettes',
            'recette_updated' => 'recettes',
            'recette_unlocked' => 'recettes',
            'recette_attachment_added' => 'recettes',
            'depense_created' => 'depenses',
            'depense_updated' => 'depenses',
            'depense_unlocked' => 'depenses',
            'depense_attachment_added' => 'depenses',
            'versement_created' => 'versement_bancaires',
            'versement_updated' => 'versement_bancaires',
            'versement_unlocked' => 'versement_bancaires',
            'versement_attachment_added' => 'versement_bancaires',
        ]);
    }
}
