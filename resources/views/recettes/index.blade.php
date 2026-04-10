@extends('layouts.app')

@section('title', 'Recettes')
@section('heading', 'Gestion des recettes')
@section('subheading', 'Saisie, consultation, modification et export')

@section('actions')
    @can('create', App\Models\Recette::class)
        <a class="btn btn-primary" href="{{ route('recettes.create') }}">Nouvelle recette</a>
    @endcan
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Gare</label>
                <select name="gare_id">
                    <option value="">Toutes</option>
                    @foreach($gares as $gare)
                        <option value="{{ $gare->id }}" @selected((string) request('gare_id') === (string) $gare->id)>{{ $gare->name }}</option>
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
                <button class="btn btn-outline" type="submit">Filtrer</button>
                <a class="btn btn-outline" href="{{ route('exports.recettes', request()->query()) }}">Exporter Excel</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gare</th>
                    <th>Montant</th>
                    <th>Référence</th>
                    <th>Saisi par</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recettes as $recette)
                    <tr>
                        <td>{{ $recette->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $recette->gare->name }}</td>
                        <td>{{ number_format($recette->amount, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $recette->reference ?: '—' }}</td>
                        <td>{{ $recette->creator->name ?? '—' }}</td>
                        <td class="actions-cell">
                            @can('update', $recette)
                                <a class="btn btn-sm btn-outline" href="{{ route('recettes.edit', $recette) }}">Modifier</a>
                            @else
                                <span class="text-muted">Verrouillée</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune recette trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $recettes->links() }}
@endsection
