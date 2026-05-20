@extends('layouts.app')

@section('title', 'Historique système')
@section('heading', 'Historique détaillé des modifications')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ request('module', $module->value ?? 'gares') }}">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Description, événement...">
            </div>
            <div>
                <label>Utilisateur</label>
                <select name="user_id">
                    <option value="">Tous</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Événement</label>
                <select name="event_type">
                    <option value="">Tous</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType }}" @selected((string) request('event_type') === (string) $eventType)>{{ $eventLabels[$eventType] ?? \Illuminate\Support\Str::headline($eventType) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Entité</label>
                <select name="entity_type">
                    <option value="">Toutes</option>
                    @foreach($entityTypes as $entityType)
                        <option value="{{ $entityType }}" @selected((string) request('entity_type') === (string) $entityType)>{{ $entityType }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Date début</label>
                <input type="date" name="start_date" value="{{ request('start_date', now('Africa/Abidjan')->toDateString()) }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ request('end_date', now('Africa/Abidjan')->toDateString()) }}">
            </div>
            <div class="align-end gap-sm">
                <button class="btn btn-outline" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Filtrer
                </button>
            </div>
        </form>
    </div>

    <div class="table-wrapper table-compact activity-logs-table">
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Date et heure</th>
                    <th>Événement</th>
                    <th>Gare</th>
                    <th>Description</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->user?->name ?? 'Système' }}</td>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $eventLabels[$log->event_type] ?? \Illuminate\Support\Str::headline($log->event_type) }}</td>
                        <td>{{ $log->gare?->name ?: 'Toutes gares' }}</td>
                        <td>{{ $log->description ?: 'Modification enregistrée.' }}</td>
                        <td>
                            <div class="activity-log-actions">
                                <a href="{{ route('activity-logs.show', array_merge(['activityLog' => $log], request()->query())) }}" class="btn btn-sm btn-outline activity-log-action-btn" title="Voir" aria-label="Voir">
                                    <span class="icon">{!! app_icon('eye') !!}</span>
                                    <span class="sr-only">Voir</span>
                                </a>
                                @if(auth()->user()->canAdministerModule($module))
                                    <form method="POST" action="{{ route('activity-logs.destroy', array_merge(['activityLog' => $log], request()->query())) }}" class="activity-log-action-form" onsubmit="return confirm('Supprimer cet historique ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger activity-log-action-btn" type="submit" title="Supprimer" aria-label="Supprimer">
                                            <span class="icon">{!! app_icon('trash') !!}</span>
                                            <span class="sr-only">Supprimer</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune modification historisée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links('partials.pagination') }}
@endsection
