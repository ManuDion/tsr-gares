@extends('layouts.app')

@section('title', 'Paramétrage banque')
@section('heading', 'Paramétrage de versement exceptionnel')
@section('subheading', 'Forcer temporairement les versements sur Coris ou Ecobank (globalement ou selon les gares ciblees)')

@section('content')
    <div class="panel">
        <form method="POST" action="{{ route('bank-routing-overrides.store', ['module' => $module->value]) }}" class="filters-grid">
            @csrf
            <div>
                <label>Banque cible</label>
                <select name="forced_account_type" required>
                    <option value="national">Coris Bank (compte national)</option>
                    <option value="inter">Ecobank (compte inter)</option>
                </select>
            </div>
            <div>
                <label>Date début</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required>
            </div>
            <div>
                <label>Date fin (optionnel)</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}">
            </div>
            <div>
                <label>Note</label>
                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Motif de l'activation">
            </div>
            <div class="col-span-2">
                <label>Gares ciblees (optionnel)</label>
                <small>Sans selection: la regle s'applique a toutes les gares du scope. Avec selection: uniquement aux gares cochees.</small>
                <div class="checkbox-grid checkbox-grid-top">
                    @foreach($gares as $gare)
                        <label class="checkbox-card">
                            <input type="checkbox" name="gare_ids[]" value="{{ $gare->id }}" @checked(in_array((string) $gare->id, collect(old('gare_ids', []))->map(fn ($id) => (string) $id)->all(), true))>
                            <span>
                                <strong>{{ $gare->name }}</strong>
                                <small>{{ $gare->city }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="align-end">
                <button class="btn btn-primary" type="submit">Activer la regle</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="entry-head">
            <h2>Regles actives</h2>
            <form method="POST" action="{{ route('bank-routing-overrides.disable-all', ['module' => $module->value]) }}" onsubmit="return confirm('Desactiver toutes les regles actives et revenir au mode normal ?');">
                @csrf
                <button class="btn btn-outline" type="submit">Retour au mode normal</button>
            </form>
        </div>
        <div class="table-wrapper table-plain">
            <table>
                <thead>
                    <tr>
                        <th>Banque forcee</th>
                        <th>Période</th>
                        <th>Gares ciblees</th>
                        <th>Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeOverrides as $override)
                        <tr>
                            <td>{{ $override->forced_account_type === 'inter' ? 'Ecobank' : 'Coris Bank' }}</td>
                            <td>
                                {{ $override->start_date?->format('d/m/Y') }}
                                au
                                {{ $override->end_date?->format('d/m/Y') ?? 'indefinie' }}
                            </td>
                            <td>
                                {{ $override->gares->isNotEmpty() ? $override->gares->pluck('name')->join(', ') : 'Toutes les gares' }}
                            </td>
                            <td>{{ $override->notes ?: '-' }}</td>
                            <td class="actions-cell">
                                <form method="POST" action="{{ route('bank-routing-overrides.disable', ['override' => $override, 'module' => $module->value]) }}" class="inline-flex-form">
                                    @csrf
                                    <input
                                        type="checkbox"
                                        class="action-toggle-input"
                                        checked
                                        title="Desactiver"
                                        aria-label="Desactiver"
                                        onchange="this.form.submit()"
                                    >
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune regle active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Historique</h2>
        <div class="table-wrapper table-plain">
            <table>
                <thead>
                    <tr>
                        <th>Statut</th>
                        <th>Banque</th>
                        <th>Période</th>
                        <th>Gares ciblees</th>
                        <th>Cree par</th>
                        <th>Mis a jour par</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $item)
                        <tr>
                            <td>
                                <span class="badge {{ $item->is_active ? 'badge-success' : 'badge-danger' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $item->forced_account_type === 'inter' ? 'Ecobank' : 'Coris Bank' }}</td>
                            <td>{{ $item->start_date?->format('d/m/Y') }} - {{ $item->end_date?->format('d/m/Y') ?? 'indefinie' }}</td>
                            <td>
                                {{ $item->gares->isNotEmpty() ? $item->gares->pluck('name')->join(', ') : 'Toutes les gares' }}
                            </td>
                            <td>{{ $item->creator?->name ?? '-' }}</td>
                            <td>{{ $item->updater?->name ?? '-' }}</td>
                            <td class="actions-cell">
                                <form method="POST" action="{{ route('bank-routing-overrides.destroy', ['override' => $item, 'module' => $module->value]) }}" onsubmit="return confirm('Supprimer cette ligne de l\\'historique ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit" title="Supprimer" aria-label="Supprimer">
                                        <span class="icon">{!! app_icon('trash') !!}</span>
                                        <span class="sr-only">Supprimer</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucun historique.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links('partials.pagination') }}
    </div>
@endsection
