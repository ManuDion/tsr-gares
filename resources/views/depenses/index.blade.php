@extends('layouts.app')

@section('title', 'Depenses')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Depenses courrier' : 'Gestion des depenses')
@section('subheading', auth()->user()->canViewAllGares($module->financialScope()) ? 'Saisie des depenses, justificatifs et export' : 'Liste des depenses de votre perimetre')

@section('actions')
    @can('create', App\Models\Depense::class)
        <a class="btn btn-primary" href="{{ route('depenses.create', ['module' => $module->value]) }}"><span class="icon">{!! app_icon('plus') !!}</span> Nouvelle depense</a>
    @endcan
@endsection

@section('content')
    @php
        $isVerificateur = auth()->user()->isVerificateur();
    @endphp

    @if(auth()->user()->canViewAllGares($module->financialScope()) || $isVerificateur)
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
                    <label>Date debut</label>
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
                        <label>Numero de telephone</label>
                        <input type="text" name="creator_phone" value="{{ request('creator_phone') }}" placeholder="Ex. 0700000000">
                    </div>
                    <div>
                        <label>Modification</label>
                        <select name="modification_state">
                            <option value="">Tous</option>
                            <option value="unlock_active" @selected(request('modification_state') === 'unlock_active')>Deverrouillage actif</option>
                            <option value="unlock_expired" @selected(request('modification_state') === 'unlock_expired')>Deverrouillage expire</option>
                            <option value="locked" @selected(request('modification_state') === 'locked')>Aucun deverrouillage</option>
                        </select>
                    </div>
                @endif
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
                    @if($isVerificateur)
                        <th>Date</th>
                        <th>Gare</th>
                        <th>Justificatif</th>
                        <th>Saisi par</th>
                        <th>Numero de telephone</th>
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
                                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif depense' }}" onclick="return window.openInternalFileViewer(this);">
                                        <span class="icon">{!! app_icon('eye') !!}</span> Lire
                                    </a>
                                    @if(auth()->user()->hasGlobalVisibility())
                                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                                            <span class="icon">{!! app_icon('download') !!}</span> Telecharger
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
                                        <small>Deverrouillee jusqu'au {{ $depense->force_unlocked_until->format('d/m/Y H:i') }} ({{ $depense->unlockedBy?->name ?? 'Superviseur' }})</small>
                                    @else
                                        <small>Deverrouillage expire le {{ $depense->force_unlocked_until->format('d/m/Y H:i') }}</small>
                                    @endif
                                @else
                                    <small>Aucun deverrouillage actif</small>
                                @endif
                                @can('update', $depense)
                                    <div class="mt-xs">
                                        <a class="btn btn-sm btn-outline" href="{{ route('depenses.edit', $depense) }}">Modifier</a>
                                    </div>
                                @endcan
                            </td>
                        @else
                            <td class="actions-cell">
                                @can('update', $depense)
                                    <a class="btn btn-sm btn-outline" href="{{ route('depenses.edit', $depense) }}">
                                        <span class="icon">{!! app_icon('edit') !!}</span>
                                        <span class="sr-only">Modifier</span>
                                    </a>
                                @else
                                    <span class="text-muted">Verrouillee</span>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6">Aucune depense trouvee.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $depenses->links('partials.pagination') }}
@endsection

