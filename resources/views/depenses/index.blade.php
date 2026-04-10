@extends('layouts.app')

@section('title', 'Dépenses')
@section('heading', 'Gestion des dépenses')
@section('subheading', 'Saisie des dépenses, justificatifs et export')

@section('actions')
    @can('create', App\Models\Depense::class)
        <a class="btn btn-primary" href="{{ route('depenses.create') }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvelle dépense</a>
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
                <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
                <a class="btn btn-outline" href="{{ route('exports.depenses', request()->query()) }}">Exporter Excel</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gare</th>
                    <th>Motif</th>
                    <th>Montant</th>
                    <th>Justificatif</th>
                </tr>
            </thead>
            <tbody>
                @forelse($depenses as $depense)
                    <tr>
                        <td>{{ $depense->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $depense->gare->name }}</td>
                        <td>{{ $depense->motif }}</td>
                        <td>{{ number_format($depense->amount, 0, ',', ' ') }} FCFA</td>
                        <td>
                            @forelse($depense->justificatives as $piece)
                                <div class="doc-links">
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" target="_blank">
                                        <span class="icon">{!! app_icon('eye') !!}</span> Lire
                                    </a>
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                                        <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                                    </a>
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune dépense trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $depenses->links() }}
@endsection
