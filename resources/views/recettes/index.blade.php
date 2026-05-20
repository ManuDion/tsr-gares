@extends('layouts.app')

@section('title', 'Recettes')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Recettes courrier' : 'Gestion des recettes')

@section('actions')
    @can('create', App\Models\Recette::class)
        <a class="btn btn-primary" href="{{ route('recettes.create', ['module' => $module->value]) }}">Nouvelle recette</a>
    @endcan
@endsection

@section('content')
    @php
        $isCourrier = ($module?->value ?? 'gares') === 'courrier';
    @endphp
        <div class="panel">
            <form method="GET" class="filters-grid recettes-filters">
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
                    <button class="btn btn-outline" type="submit">Filtrer</button>
                    <a class="btn btn-outline" href="{{ route('exports.recettes', array_merge(request()->query(), ['module' => $module->value])) }}">Exporter Excel</a>
                </div>
            </form>
        </div>

    <div class="table-wrapper table-compact recettes-table">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gare</th>
                    <th>Composition<br>recettes</th>
                    <th>Justificatif</th>
                    <th>Saisi par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recettes as $recette)
                    <tr>
                        <td data-label="Date">{{ $recette->operation_date?->format('d/m/Y') }}</td>
                        <td data-label="Gare" title="{{ $recette->gare->name }}"><span class="cell-truncate">{{ $recette->gare->name }}</span></td>
                        <td data-label="Composition recettes">
                            @if($isCourrier)
                                <div class="breakdown-summary">
                                    <span>RC : {{ number_format($recette->amount, 0, '', ' ') }}</span>
                                </div>
                            @else
                                <div class="breakdown-summary">
                                    <span>TI : {{ number_format($recette->ticket_inter_amount, 0, '', ' ') }}</span>
                                    <span>TN : {{ number_format($recette->ticket_national_amount, 0, '', ' ') }}</span>
                                    <span>BI : {{ number_format($recette->bagage_inter_amount, 0, '', ' ') }}</span>
                                    <span>BN : {{ number_format($recette->bagage_national_amount, 0, '', ' ') }}</span>
                                </div>
                            @endif
                        </td>
                        <td data-label="Justificatif">
                            @forelse($recette->justificatives as $piece)
                                <div class="doc-links">
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif recette' }}" onclick="return window.openInternalFileViewer(this);" title="Voir" aria-label="Voir">
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
                        <td data-label="Saisi par" title="{{ $recette->creator->name ?? '-' }}"><span class="cell-truncate">{{ $recette->creator->name ?? '-' }}</span></td>
                        <td class="actions-cell" data-label="Actions">
                            @can('update', $recette)
                                <a class="btn btn-sm btn-outline" href="{{ route('recettes.edit', $recette) }}" title="Modifier" aria-label="Modifier">
                                    <span class="icon">{!! app_icon('edit') !!}</span>
                                    <span class="sr-only">Modifier</span>
                                </a>
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

    {{ $recettes->links('partials.pagination') }}
@endsection
