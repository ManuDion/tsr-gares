@extends('layouts.app')

@section('title', 'Gares · TSR Gares Finance')
@section('heading', 'Gestion des gares')
@section('subheading', 'Recherche, consultation et administration des gares')

@section('actions')
    @can('create', App\Models\Gare::class)
        <a class="btn btn-primary" href="{{ route('gares.create') }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvelle gare</a>
    @endcan
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Code, ville ou nom">
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
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Ville</th>
                    <th>Zone</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($gares as $gare)
                    <tr>
                        <td>{{ $gare->code }}</td>
                        <td>{{ $gare->name }}</td>
                        <td>{{ $gare->city }}</td>
                        <td>{{ $gare->zone ?: '—' }}</td>
                        <td><span class="badge {{ $gare->is_active ? 'badge-success' : 'badge-danger' }}">{{ $gare->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="actions-cell">
                            <a class="btn btn-sm btn-outline" href="{{ route('gares.show', $gare) }}">Voir</a>
                            @can('update', $gare)
                                <a class="btn btn-sm btn-outline" href="{{ route('gares.edit', $gare) }}">Modifier</a>
                                <form method="POST" action="{{ route('gares.toggle-active', $gare) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline" type="submit">{{ $gare->is_active ? 'Désactiver' : 'Activer' }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune gare trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $gares->links() }}
@endsection
