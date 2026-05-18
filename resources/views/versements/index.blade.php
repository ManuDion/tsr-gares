@extends('layouts.app')

@section('title', 'Versements bancaires')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Versements courrier' : 'Gestion des versements bancaires')
@section('subheading', auth()->user()->canViewAllGares($module->financialScope()) ? 'Suivi des dépôts bancaires et des bordereaux justificatifs' : 'Liste des versements de votre périmètre')

@section('actions')
    @can('create', App\Models\VersementBancaire::class)
        <a class="btn btn-primary" href="{{ route('versements.create', ['module' => $module->value]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouveau versement</a>
    @endcan
@endsection

@section('content')
    @php
        $isVerificateur = auth()->user()->isVerificateur();
    @endphp

    @if(auth()->user()->canViewAllGares($module->financialScope()) || $isVerificateur)
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
                    <button class="btn btn-outline" type="submit"><span class="icon">{!! app_icon('filter') !!}</span> Filtrer</button>
                    <a class="btn btn-outline" href="{{ route('exports.controls', request()->query()) }}">Exporter contrôles</a>
                </div>
            </form>
        </div>
    @endif

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
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Bordereau versement' }}" onclick="return window.openInternalFileViewer(this);">
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
                        <td class="actions-cell" data-label="Action">
                            @can('update', $versement)
                                <a class="btn btn-sm btn-outline" href="{{ route('versements.edit', $versement) }}">
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
