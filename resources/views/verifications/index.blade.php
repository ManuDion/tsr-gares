@extends('layouts.app')

@section('title', 'Vérification')
@section('heading', 'Module de vérification')
@section('subheading', 'Contrôle par gare : Versement = Recette - Dépense')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <div>
                <label>Date d’opération</label>
                <input type="date" name="operation_date" value="{{ $operationDate }}">
            </div>
            <div>
                <label>Statut</label>
                <select name="status">
                    <option value="">Tous</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="align-end">
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Vérifier
                </button>
            </div>
        </form>
    </div>

    @if(auth()->user()->isAdmin())
        <div class="panel">
            <form method="POST" action="{{ route('verifications.purge-period') }}" class="filters-grid" onsubmit="return confirm('Supprimer les vérifications sur cette période ?');">
                @csrf
                @method('DELETE')
                <div>
                    <label>Date début</label>
                    <input type="date" name="start_date" required>
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="align-end">
                    <button class="btn btn-outline" type="submit">Supprimer sur la période</button>
                </div>
            </form>
        </div>
    @endif

    <div class="table-wrapper table-compact verification-table verification-table--compact">
        <table>
            <thead>
                <tr>
                    <th>Gare</th>
                    <th>Date</th>
                    <th><span class="th-stack">Recettes<small>en FCFA</small></span></th>
                    <th><span class="th-stack">Dépenses<small>en FCFA</small></span></th>
                    <th><span class="th-stack">Versements<small>en FCFA</small></span></th>
                    <th><span class="th-stack">Attendu<small>en FCFA</small></span></th>
                    <th><span class="th-stack">Écart<small>en FCFA</small></span></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($checks as $check)
                    @php
                        $difference = (float) $check->difference;
                        $badgeClass = abs($difference) < 0.01 ? 'badge-success' : 'badge-danger';
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $check->gare?->name ?? '—' }}</strong>
                        </td>
                        <td>{{ $check->operation_date?->format('d/m/Y') }}</td>
                        <td class="amount-cell">{{ number_format($check->recettes_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->depenses_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->versements_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->expected_versement, 0, ',', ' ') }}</td>
                        <td class="amount-cell">
                            <span class="badge {{ $badgeClass }}">{{ number_format($difference, 0, ',', ' ') }}</span>
                            @if($check->reviewer)
                                <div class="text-muted compact-note">
                                    {{ $statuses[$check->status] ?? \Illuminate\Support\Str::headline($check->status) }}
                                    · {{ $check->reviewer->name }}
                                    · {{ $check->reviewed_at?->format('d/m/Y H:i') }}
                                </div>
                            @endif
                        </td>
                        <td class="verification-actions">
                            @if(abs($difference) < 0.01)
                                <span class="text-muted">Aucune action</span>
                            @else
                                <form method="POST" action="{{ route('verifications.confirm', $check) }}" class="form-inline form-inline-compact">
                                    @csrf
                                    <input type="text" name="review_note" placeholder="Note">
                                    <button class="btn btn-sm btn-outline icon-only-btn" type="submit" title="Confirmer l'écart" aria-label="Confirmer l'écart">
                                        <span class="icon">{!! app_icon('checklist') !!}</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('verifications.enable-adjustments', $check) }}" class="form-inline form-inline-compact">
                                    @csrf
                                    <input type="text" name="review_note" placeholder="Instruction">
                                    <button class="btn btn-sm btn-primary icon-only-btn" type="submit" title="Activer les modifications" aria-label="Activer les modifications">
                                        <span class="icon">{!! app_icon('edit') !!}</span>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Aucune vérification pour cette date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $checks->links('partials.pagination') }}
@endsection
