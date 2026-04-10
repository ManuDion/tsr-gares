@extends('layouts.app')

@section('title', 'Versements bancaires')
@section('heading', 'Gestion des versements bancaires')
@section('subheading', 'Suivi des dépôts bancaires, bordereaux et lecture OCR')

@section('actions')
    @can('create', App\Models\VersementBancaire::class)
        <a class="btn btn-primary" href="{{ route('versements.create') }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouveau versement</a>
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
                <a class="btn btn-outline" href="{{ route('exports.controls', request()->query()) }}">Exporter contrôles</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date opération</th>
                    <th>Date recette</th>
                    <th>Gare</th>
                    <th>Banque</th>
                    <th>Montant</th>
                    <th>Référence</th>
                    <th>Bordereau</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($versements as $versement)
                    <tr>
                        <td>{{ $versement->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $versement->receipt_date?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $versement->gare->name }}</td>
                        <td>{{ $versement->bank_name ?: '—' }}</td>
                        <td>{{ number_format($versement->amount, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $versement->reference ?: '—' }}</td>
                        <td>
                            @forelse($versement->justificatives as $piece)
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
                        <td>
                            @can('update', $versement)
                                <a class="btn btn-sm btn-outline" href="{{ route('versements.edit', $versement) }}">Modifier</a>
                            @else
                                —
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Aucun versement trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $versements->links() }}
@endsection
