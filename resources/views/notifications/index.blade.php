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
        ];
    @endphp

    <div class="panel">
        <div class="hero-inline">
            <div>
                <h2>Notifications actives</h2>
                <p class="text-muted">Chaque utilisateur voit uniquement les notifications qui le concernent selon son rôle et ses gares autorisées.</p>
            </div>
            <span class="badge badge-success">Synchronisé</span>
        </div>
    </div>

    <div class="table-wrapper">
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
                    <tr>
                        <td>{{ $notification->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <strong>{{ $notification->subject }}</strong>
                            <div class="text-muted">{{ $notification->content }}</div>
                        </td>
                        <td>{{ collect($notification->gares)->implode(', ') ?: '—' }}</td>
                        <td>
                            {{
                                collect($notification->operations)
                                    ->map(fn ($operation) => $operationLabels[$operation] ?? \Illuminate\Support\Str::headline($operation))
                                    ->implode(', ') ?: '—'
                            }}
                        </td>
                        <td>
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
