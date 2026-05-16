@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Historique des notifications')
@section('subheading', 'Suivi des anomalies, rappels et contrôles journaliers')

@section('content')
    @php
        $statusLabels = [
            'generated' => 'Générée',
            'read' => 'Lue',
            'sent' => 'Envoyée',
        ];

        $operationLabels = [
            'recette' => 'Recette',
            'depense' => 'Dépense',
            'versement_bancaire' => 'Versement bancaire',
            'validation_caissier' => 'Validation caissier',
        ];
    @endphp

    <div class="panel">
        <form method="GET" class="filters-grid notifications-filters">
            <input type="hidden" name="module" value="{{ request('module', $module->value ?? 'gares') }}">
            <div>
                <label>Période</label>
                <select name="period">
                    <option value="" @selected(($period ?? '') === '')>Toutes</option>
                    <option value="today" @selected(($period ?? '') === 'today')>Aujourd'hui</option>
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
                <a class="btn btn-outline" href="{{ route('notifications.index', ['module' => request('module', $module->value ?? 'gares')]) }}">Réinitialiser</a>
                <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
            </div>
        </form>
    </div>

    @if(auth()->user()->canAdministerModule($module))
        <div class="panel">
            <form method="POST" action="{{ route('notifications.purge-period') }}" class="filters-grid" onsubmit="return confirm('Supprimer les historiques de notifications sur cette période ?');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="module" value="{{ request('module', $module->value ?? 'gares') }}">
                <div>
                    <label>Date début</label>
                    <input type="date" name="start_date" required>
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="align-end">
                    <button class="btn btn-outline" type="submit">Supprimer sur la période</button>
                </div>
            </form>
        </div>
    @endif

    <div class="table-wrapper table-compact notifications-table">
        <table>
            <thead>
                <tr>
                    <th>Date génération</th>
                    <th>Objet</th>
                    <th>Gares</th>
                    <th>Opérations</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notification)
                    @php($garesLabel = collect($notification->gares)->implode(', ') ?: '—')
                    @php(
                        $operationsLabel = collect($notification->operations)
                            ->map(fn ($operation) => $operationLabels[$operation] ?? \Illuminate\Support\Str::headline($operation))
                            ->implode(', ') ?: '—'
                    )
                    <tr>
                        <td data-label="Date génération">{{ $notification->created_at?->format('d/m/Y H:i') }}</td>
                        <td data-label="Objet">
                            <strong class="notification-subject-title" title="{{ $notification->subject }}">{{ $notification->subject }}</strong>
                            <div class="text-muted notification-subject-content" title="{{ $notification->content }}">{{ $notification->content }}</div>
                        </td>
                        <td data-label="Gares" title="{{ $garesLabel }}"><span class="cell-truncate">{{ $garesLabel }}</span></td>
                        <td data-label="Opérations" title="{{ $operationsLabel }}"><span class="cell-truncate">{{ $operationsLabel }}</span></td>
                        <td data-label="Statut">
                            <span class="badge {{ $notification->status === 'generated' ? 'badge-success' : '' }}">
                                {{ $statusLabels[$notification->status] ?? \Illuminate\Support\Str::headline($notification->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune notification enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $notifications->links('partials.pagination') }}
@endsection

