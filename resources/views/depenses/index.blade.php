@extends('layouts.app')

@section('title', 'Dépenses')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Dépenses courrier' : 'Gestion des dépenses')

@section('actions')
    @can('create', App\Models\Depense::class)
        <a class="btn btn-primary" href="{{ route('depenses.create', ['module' => $module->value]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvelle dépense</a>
    @endcan
@endsection

@section('content')
    @php
        $isVerificateur = auth()->user()->isVerificateur();
    @endphp

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
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    @if($isVerificateur)
                        <th>Date</th>
                        <th>Gare</th>
                        <th>Justificatif</th>
                        <th>Saisi par</th>
                        <th>Numéro de téléphone</th>
                        <th>Modification</th>
                    @else
                        <th>Date</th>
                        <th>Gare</th>
                        <th>Motif</th>
                        <th><span class="th-stack">Montant<small>en FCFA</small></span></th>
                        <th>Justificatif</th>
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($depenses as $depense)
                    <tr>
                        <td>{{ $depense->operation_date?->format('d/m/Y') }}</td>
                        <td>{{ $depense->gare->name }}</td>
                        @unless($isVerificateur)
                            <td>{{ $depense->motif }}</td>
                            <td class="amount-cell">{{ number_format($depense->amount, 0, '', ' ') }}</td>
                        @endunless
                        <td>
                            @forelse($depense->justificatives as $piece)
                                <div class="doc-links">
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif dépense' }}" onclick="return window.openInternalFileViewer(this);" title="Voir" aria-label="Voir">
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
                        @if($isVerificateur)
                            <td>{{ $depense->creator->name ?? '-' }}</td>
                            <td>{{ $depense->creator->phone ?? '-' }}</td>
                            <td>
                                @if($depense->force_unlocked_until)
                                    @if($depense->force_unlocked_until->isFuture())
                                        <small>Déverrouillée jusqu'au {{ $depense->force_unlocked_until->format('d/m/Y H:i') }} ({{ $depense->unlockedBy?->name ?? 'Superviseur' }})</small>
                                    @else
                                        <small>Déverrouillage expiré le {{ $depense->force_unlocked_until->format('d/m/Y H:i') }}</small>
                                    @endif
                                @else
                                    <small>Aucun déverrouillage actif</small>
                                @endif
                                @can('update', $depense)
                                    <div class="mt-xs">
                                        <a class="btn btn-sm btn-outline" href="{{ route('depenses.edit', $depense) }}" title="Modifier" aria-label="Modifier">
                                        <span class="icon">{!! app_icon('edit') !!}</span>
                                        <span class="sr-only">Modifier</span>
                                    </a>
                                    </div>
                                @endcan
                            </td>
                        @else
                            <td class="actions-cell">
                                @can('update', $depense)
                                    <a class="btn btn-sm btn-outline" href="{{ route('depenses.edit', $depense) }}" title="Modifier" aria-label="Modifier">
                                        <span class="icon">{!! app_icon('edit') !!}</span>
                                        <span class="sr-only">Modifier</span>
                                    </a>
                                @else
                                    <span class="text-muted">Verrouillée</span>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune dépense trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $depenses->links('partials.pagination') }}
@endsection
