@extends('layouts.app')

@section('title', 'Dépenses')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Dépenses courrier' : 'Gestion des dépenses')
@section('subheading', auth()->user()->canViewAllGares() ? 'Saisie des dépenses, justificatifs et export' : 'Liste des dépenses de votre périmètre')

@section('actions')
    @can('create', App\Models\Depense::class)
        <a class="btn btn-primary" href="{{ route('depenses.create', ['module' => $module->value]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvelle dépense</a>
    @endcan
@endsection

@section('content')
    @if(auth()->user()->canViewAllGares())
        <div class="panel">
            <form method="GET" class="filters-grid">
                <input type="hidden" name="module" value="{{ $module->value }}">
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
                    <a class="btn btn-outline" href="{{ route('exports.depenses', array_merge(request()->query(), ['module' => $module->value])) }}">Exporter Excel</a>
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
                    <th>Motif</th>
                    <th><span class="th-stack">Montant<small>en FCFA</small></span></th>
                    <th>Justificatif</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($depenses as $depense)
                    <tr>
                        <td>{{ $depense->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $depense->gare->name }}</td>
                        <td>{{ $depense->motif }}</td>
                        <td class="amount-cell">{{ number_format($depense->amount, 0, ',', ' ') }}</td>
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
                        <td class="actions-cell">
                            @can('update', $depense)
                                <a class="btn btn-sm btn-outline" href="{{ route('depenses.edit', $depense) }}">
                                    <span class="icon">{!! app_icon('edit') !!}</span>
                                    <span class="sr-only">Modifier</span>
                                </a>
                            @else
                                <span class="text-muted">Verrouillée</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune dépense trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $depenses->links('partials.pagination') }}
@endsection
