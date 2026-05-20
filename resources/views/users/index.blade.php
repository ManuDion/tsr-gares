@extends('layouts.app')

@section('title', 'Utilisateurs')
@section('heading', 'Gestion des utilisateurs')
@section('subheading', 'Création des comptes, affectation aux services et activation des accès.')

@section('actions')
    @can('create', App\Models\User::class)
        <a class="btn btn-primary" href="{{ route('users.create', ['module' => request('module', 'gares')]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvel utilisateur</a>
    @endcan
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ request('module', 'gares') }}">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom, téléphone ou email">
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper table-compact users-table">
        <table>
            <thead>
                <tr>
                    <th>Nom complet</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Service</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td title="{{ $user->name }}"><span class="cell-truncate">{{ $user->name }}</span></td>
                        <td>{{ $user->phone ?? '—' }}</td>
                        <td title="{{ $user->email }}"><span class="cell-truncate">{{ $user->email }}</span></td>
                        <td title="{{ $user->roleLabel() }}"><span class="cell-truncate">{{ $user->roleLabel() }}</span></td>
                        <td title="{{ $user->department?->moduleLabel() ?? '—' }}"><span class="cell-truncate">{{ $user->department?->moduleLabel() ?? '—' }}</span></td>
                        <td>
                            <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $user->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-outline" href="{{ route('users.edit', ['user' => $user, 'module' => request('module', 'gares')]) }}" title="Modifier" aria-label="Modifier">
                                <span class="icon">{!! app_icon('edit') !!}</span>
                                <span class="sr-only">Modifier</span>
                            </a>
                            <form method="POST" action="{{ route('users.toggle-active', ['user' => $user, 'module' => request('module', 'gares')]) }}" class="inline-flex-form">
                                @csrf
                                <input
                                    type="checkbox"
                                    class="action-toggle-input"
                                    @checked($user->is_active)
                                    @disabled($user->is(auth()->user()) && $user->is_active)
                                    title="{{ $user->is_active ? 'Desactiver' : 'Activer' }}"
                                    aria-label="{{ $user->is_active ? 'Desactiver' : 'Activer' }}"
                                    onchange="this.form.submit()"
                                >
                            </form>
                            <form method="POST" action="{{ route('users.destroy', ['user' => $user, 'module' => request('module', 'gares')]) }}" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit" title="Supprimer" aria-label="Supprimer">
                                    <span class="icon">{!! app_icon('trash') !!}</span>
                                    <span class="sr-only">Supprimer</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun utilisateur trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links('partials.pagination') }}
@endsection

