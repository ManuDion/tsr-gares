@extends('layouts.app')

@section('title', 'Vérification')
@section('heading', 'Module de vérification')
@section('subheading', ($module?->value ?? 'gares') === 'courrier' ? 'Contrôle du service courrier : versement = recette - dépense' : 'Contrôle par gare : versement = recette - dépense')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date opération</label>
                <input type="date" name="operation_date" value="{{ $operationDate }}">
            </div>
            <div>
                <label>Gare</label>
                <select name="gare_id">
                    <option value="">Toutes</option>
                    @foreach(($gares ?? collect()) as $gare)
                        <option value="{{ $gare->id }}" @selected((string) request('gare_id') === (string) $gare->id)>{{ $gare->name }}</option>
                    @endforeach
                </select>
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

    @if(auth()->user()->canAdministerModule($module))
        <div class="panel">
            <form method="POST" action="{{ route('verifications.purge-period', ['module' => $module->value]) }}" class="filters-grid" onsubmit="return confirm('Supprimer les vérifications sur cette période ?');">
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
                    <th><span class="th-stack">Recettes<small>(Int/Nat)</small></span></th>
                    <th><span class="th-stack">Dépenses<small>(Int/Nat)</small></span></th>
                    <th><span class="th-stack">Attendu<small>(I/N)</small></span></th>
                    <th><span class="th-stack">Versements<small>(I/N)</small></span></th>
                    <th><span class="th-stack">Écart<small>(I/N)</small></span></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($checks as $check)
                    @php
                        $difference = (int) $check->difference;
                        $differenceInter = (int) $check->difference_inter;
                        $differenceNational = (int) $check->difference_national;
                        $isOk = $difference === 0 && $differenceInter === 0 && $differenceNational === 0;
                        $badgeClass = $isOk ? 'badge-success' : 'badge-danger';
                    @endphp
                    <tr>
                        <td><strong>{{ $check->gare?->name ?? '-' }}</strong></td>
                        <td>{{ $check->operation_date?->format('d/m/Y') }}</td>
                        <td class="amount-cell">{{ number_format($check->recettes_inter_total, 0, '', ' ') }} / {{ number_format($check->recettes_national_total, 0, '', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->depenses_inter_total, 0, '', ' ') }} / {{ number_format($check->depenses_national_total, 0, '', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->expected_inter_versement, 0, '', ' ') }} / {{ number_format($check->expected_national_versement, 0, '', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->versements_inter_total, 0, '', ' ') }} / {{ number_format($check->versements_national_total, 0, '', ' ') }}</td>
                        <td class="amount-cell">
                            <span class="badge {{ $badgeClass }}">{{ number_format($difference, 0, '', ' ') }}</span>
                            <div class="text-muted compact-note">
                                I: {{ number_format($differenceInter, 0, '', ' ') }} | N: {{ number_format($differenceNational, 0, '', ' ') }}
                            </div>
                            @if($check->reviewer)
                                <div class="text-muted compact-note verification-review-meta">
                                    <span>{{ $statuses[$check->status] ?? \Illuminate\Support\Str::headline($check->status) }}</span>
                                    <span>{{ $check->reviewer->name }}</span>
                                    <span>{{ $check->reviewed_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="verification-actions">
                            @if($isOk)
                                <span class="text-muted">Aucune action</span>
                            @else
                                <form method="POST" action="{{ route('verifications.confirm', ['verification' => $check, 'module' => $module->value]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline" type="submit">Validation</button>
                                </form>
                                <form method="POST" action="{{ route('verifications.enable-adjustments', ['verification' => $check, 'module' => $module->value]) }}">
                                    @csrf
                                    <div class="unlock-controls">
                                        <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
                                        <select name="unlock_unit" class="unlock-unit" required>
                                            <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                                            <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                                            <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
                                        </select>
                                        <button class="btn btn-sm btn-primary" type="submit">Modification</button>
                                    </div>
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
