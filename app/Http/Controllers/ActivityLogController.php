<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isResponsable(), 403);

        $query = ActivityLog::query()
            ->with(['user', 'gare'])
            ->whereNotNull('before')
            ->whereNotNull('after')
            ->latest('id');

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

        return view('activity-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => ActivityLog::query()->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
            'entityTypes' => ActivityLog::query()->select('entity_type')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'eventLabels' => $eventLabels,
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
        ]);
    }
}
