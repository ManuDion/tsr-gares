@extends('layouts.app')

@section('title', 'Recettes')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Recettes courrier' : 'Gestion des recettes')
@section('subheading', ($module?->value ?? 'gares') === 'courrier' ? 'Recettes du service courrier par gare et par période' : (auth()->user()->canViewAllGares($module->financialScope()) ? 'Saisie, consultation, modification et export' : 'Recettes visibles et modifiables dans votre périmètre'))

@section('actions')
    @can('create', App\Models\Recette::class)
        <a class="btn btn-primary" href="{{ route('recettes.create', ['module' => $module->value]) }}">Nouvelle recette</a>
    @endcan
@endsection

@section('content')
    @php
        $isCourrier = ($module?->value ?? 'gares') === 'courrier';
        $isVerificateur = auth()->user()->isVerificateur();
    @endphp
    @if(auth()->user()->canViewAllGares($module->financialScope()) || $isVerificateur)
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
                    <input type="date" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}">
                </div>
                @if($isVerificateur)
                    <div>
                        <label>Saisi par</label>
                        <input type="text" name="creator_name" value="{{ request('creator_name') }}" placeholder="Nom utilisateur">
                    </div>
                    <div>
                        <label>Numéro de téléphone</label>
                        <input type="text" name="creator_phone" value="{{ request('creator_phone') }}" placeholder="Ex. 0700000000">
                    </div>
                    <div>
                        <label>Modification</label>
                        <select name="modification_state">
                            <option value="">Tous</option>
                            <option value="unlock_active" @selected(request('modification_state') === 'unlock_active')>Déverrouillage actif</option>
                            <option value="unlock_expired" @selected(request('modification_state') === 'unlock_expired')>Déverrouillage expiré</option>
                            <option value="locked" @selected(request('modification_state') === 'locked')>Aucun déverrouillage</option>
                        </select>
                    </div>
                @endif
                <div class="align-end gap-sm">
                    <button class="btn btn-outline" type="submit">Filtrer</button>
                    <a class="btn btn-outline" href="{{ route('exports.recettes', array_merge(request()->query(), ['module' => $module->value])) }}">Exporter Excel</a>
                </div>
            </form>
        </div>
    @endif

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
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif recette' }}" onclick="return window.openInternalFileViewer(this);">
                                        <span class="icon">{!! app_icon('eye') !!}</span> Lire
                                    </a>
                                    @if(auth()->user()->hasGlobalVisibility())
                                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                                            <span class="icon">{!! app_icon('download') !!}</span> Télécharger
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
                    <tr><td colspan="6">Aucune recette trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $recettes->links('partials.pagination') }}
@endsection
