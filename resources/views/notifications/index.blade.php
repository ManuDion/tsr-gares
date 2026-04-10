@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Historique des notifications')
@section('subheading', 'Suivi des anomalies, rappels et contrôles journaliers')

@section('content')
    <div class="panel">
        <div class="hero-inline">
            <div>
                <h2>Notifications actives</h2>
                <p class="text-muted">Le contrôle journalier du jour précédent est automatiquement recalculé à l’ouverture de cet écran.</p>
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
                    <th>Destinataire</th>
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
                        <td>{{ $notification->user->name ?? 'Système' }}</td>
                        <td>{{ collect($notification->gares)->implode(', ') ?: '—' }}</td>
                        <td>{{ collect($notification->operations)->implode(', ') ?: '—' }}</td>
                        <td><span class="badge {{ $notification->status === 'generated' ? 'badge-success' : '' }}">{{ ucfirst($notification->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune notification enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $notifications->links() }}
@endsection
