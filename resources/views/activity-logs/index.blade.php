@extends('layouts.app')

@section('title', 'Historique système')
@section('heading', 'Historique détaillé des modifications')
@section('subheading', 'Journal centralisé des mises à jour réellement effectuées dans l’application')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Objet, description, événement...">
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
                <input type="date" name="start_date" value="{{ request('start_date') }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}">
            </div>
            <div class="align-end gap-sm">
                <button class="btn btn-outline" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Filtrer
                </button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Objet</th>
                    <th>Utilisateur</th>
                    <th>Date et heure</th>
                    <th>Événement</th>
                    <th>Gare</th>
                    <th>Description</th>
                    <th>Détail</th>
                    @if(auth()->user()->isAdmin())
                        <th>Suppression</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td><strong>{{ $log->subject }}</strong></td>
                        <td>{{ $log->user?->name ?? 'Système' }}</td>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $eventLabels[$log->event_type] ?? \Illuminate\Support\Str::headline($log->event_type) }}</td>
                        <td>{{ $log->gare?->name ?: 'Toutes gares' }}</td>
                        <td>{{ $log->description ?: 'Modification enregistrée.' }}</td>
                        <td>
                            <a href="{{ route('activity-logs.show', $log) }}" class="btn btn-sm btn-outline">
                                <span class="icon">{!! app_icon('eye') !!}</span> Voir
                            </a>
                        </td>
                        @if(auth()->user()->isAdmin())
                            <td>
                                <form method="POST" action="{{ route('activity-logs.destroy', $log) }}" onsubmit="return confirm('Supprimer cet historique ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">
                                        <span class="icon">{!! app_icon('trash') !!}</span> Supprimer
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}">Aucune modification historisée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links('partials.pagination') }}
@endsection
