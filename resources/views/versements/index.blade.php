@extends('layouts.app')

@section('title', 'Versements bancaires')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Versements courrier' : 'Gestion des versements bancaires')

@section('actions')
    @can('create', App\Models\VersementBancaire::class)
        <a class="btn btn-primary" href="{{ route('versements.create', ['module' => $module->value]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouveau versement</a>
    @endcan
@endsection

@section('content')
        <div class="panel">
            <form method="GET" class="filters-grid versements-filters">
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
                    <input type="date" name="start_date" value="{{ request('start_date', now('Africa/Abidjan')->toDateString()) }}">
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" value="{{ request('end_date', now('Africa/Abidjan')->toDateString()) }}">
                </div>
                <div class="align-end gap-sm">
                    <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
                    <a class="btn btn-outline" href="{{ route('exports.controls', request()->query()) }}">Exporter contrôles</a>
                </div>
            </form>
        </div>
    <div class="table-wrapper table-compact versements-table">
        <table>
            <thead>
                <tr>
                    <th><span class="th-stack">Date<small>opération</small></span></th>
                    <th><span class="th-stack">Date<small>recette</small></span></th>
                    <th>Gare</th>
                    <th>Banque</th>
                    <th>Montant en FCFA</th>
                    <th>Bordereau</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($versements as $versement)
                    <tr>
                        <td data-label="Date opération">{{ $versement->operation_date?->format('d/m/Y') }}</td>
                        <td data-label="Date recette">{{ $versement->receipt_date?->format('d/m/Y') ?: '-' }}</td>
                        <td data-label="Gare" title="{{ $versement->gare->name }}"><span class="cell-truncate">{{ $versement->gare->name }}</span></td>
                        <td data-label="Banque" title="{{ $versement->bank_name ?: '-' }}"><span class="cell-truncate">{{ $versement->bank_name ?: '-' }}</span></td>
                        <td class="amount-cell amount-nowrap" data-label="Montant en FCFA">{{ number_format($versement->amount, 0, '', ' ') }}</td>
                        <td data-label="Bordereau">
                            @forelse($versement->justificatives as $piece)
                                <div class="doc-links">
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Bordereau versement' }}" onclick="return window.openInternalFileViewer(this);" title="Voir" aria-label="Voir">
                                        <span class="icon">{!! app_icon('eye') !!}</span>
                                        <span class="sr-only">Voir</span>
                                    </a>
                                    @if(auth()->user()->hasGlobalVisibility())
                                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}" title="Télécharger" aria-label="Télécharger">
                                            <span class="icon">{!! app_icon('download') !!}</span>
                                            <span class="sr-only">Télécharger</span>
                                        </a>
                                    @endif
                                </div>
                            @empty
                                -
                            @endforelse
                        </td>
                        <td class="actions-cell" data-label="Action">
                            @can('update', $versement)
                                <a class="btn btn-sm btn-outline" href="{{ route('versements.edit', $versement) }}" title="Modifier" aria-label="Modifier">
                                    <span class="icon">{!! app_icon('edit') !!}</span>
                                    <span class="sr-only">Modifier</span>
                                </a>
                            @else
                                -
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun versement trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $versements->links('partials.pagination') }}
@endsection
