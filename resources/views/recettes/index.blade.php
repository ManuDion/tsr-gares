@extends('layouts.app')

@section('title', 'Recettes')
@section('heading', 'Gestion des recettes')
@section('subheading', auth()->user()->canViewAllGares() ? 'Saisie, consultation, modification et export' : 'Recettes visibles et modifiables dans votre périmètre')

@section('actions')
    @can('create', App\Models\Recette::class)
        <a class="btn btn-primary" href="{{ route('recettes.create') }}">Nouvelle recette</a>
    @endcan
@endsection

@section('content')
    @if(auth()->user()->canViewAllGares())
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
    @endif

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gare</th>
                    <th><span class="th-stack">Montant<small>en FCFA</small></span></th>
                    <th>Composition</th>
                    <th>Justificatif</th>
                    <th>Saisi par</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recettes as $recette)
                    <tr>
                        <td>{{ $recette->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $recette->gare->name }}</td>
                        <td class="amount-cell">{{ number_format($recette->amount, 0, ',', ' ') }}</td>
                        <td>
                            <div class="breakdown-summary">
                                <span>TI : {{ number_format($recette->ticket_inter_amount, 0, ',', ' ') }}</span>
                                <span>TN : {{ number_format($recette->ticket_national_amount, 0, ',', ' ') }}</span>
                                <span>BI : {{ number_format($recette->bagage_inter_amount, 0, ',', ' ') }}</span>
                                <span>BN : {{ number_format($recette->bagage_national_amount, 0, ',', ' ') }}</span>
                            </div>
                        </td>
                        <td>
                            @forelse($recette->justificatives as $piece)
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
                        <td>{{ $recette->creator->name ?? '—' }}</td>
                        <td class="actions-cell">
                            @can('update', $recette)
                                <a class="btn btn-sm btn-outline" href="{{ route('recettes.edit', $recette) }}">
                                    <span class="icon">{!! app_icon('edit') !!}</span>
                                    <span class="sr-only">Modifier</span>
                                </a>
                            @else
                                <span class="text-muted">Verrouillée</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucune recette trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $recettes->links('partials.pagination') }}
@endsection
