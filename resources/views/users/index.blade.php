@extends('layouts.app')

@section('title', 'Utilisateurs')
@section('heading', 'Gestion des utilisateurs')
@section('subheading', 'Administration des comptes et affectations')

@section('actions')
    @can('create', App\Models\User::class)
        <a class="btn btn-primary" href="{{ route('users.create') }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvel utilisateur</a>
    @endcan
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom ou email">
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Gare principale</th>
                    <th>Gares zone</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roleLabel() }}</td>
                        <td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">{{ $user->is_active ? 'Actif' : 'Inactif' }}</span></td>
                        <td>{{ $user->primaryGare->name ?? '—' }}</td>
                        <td>{{ $user->gares->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-outline" href="{{ route('users.edit', $user) }}">Modifier</a>
                            <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline" type="submit">{{ $user->is_active ? 'Désactiver' : 'Activer' }}</button>
                            </form>
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun utilisateur trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
