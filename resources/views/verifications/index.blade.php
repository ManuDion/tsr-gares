@extends('layouts.app')

@section('title', 'Verification')
@section('heading', 'Module de verification')
@section('subheading', ($module?->value ?? 'gares') === 'courrier' ? 'Controle du service courrier: versement = recette - depense' : 'Controle par gare: versement = recette - depense')

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date operation</label>
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
                    <span class="icon">{!! app_icon('filter') !!}</span> Verifier
                </button>
            </div>
        </form>
    </div>

    @if(auth()->user()->isAdmin())
        <div class="panel">
            <form method="POST" action="{{ route('verifications.purge-period', ['module' => $module->value]) }}" class="filters-grid" onsubmit="return confirm('Supprimer les verifications sur cette periode ?');">
                @csrf
                @method('DELETE')
                <div>
                    <label>Date debut</label>
                    <input type="date" name="start_date" required>
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" required>
                </div>
                <div class="align-end">
                    <button class="btn btn-outline" type="submit">Supprimer sur la periode</button>
                </div>
            </form>
        </div>
    @endif

    <div class="table-wrapper table-compact verification-table verification-table--compact">
        <table>
            <thead>
                <tr>
                    <th>Gare</th>
                    <th>Mode</th>
                    <th>Date</th>
                    <th>Recettes (I/N)</th>
                    <th>Depenses (I/N)</th>
                    <th>Attendu (I/N)</th>
                    <th>Versements (I/N)</th>
                    <th>Ecart (I/N)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($checks as $check)
                    @php
                        $difference = (float) $check->difference;
                        $differenceInter = (float) $check->difference_inter;
                        $differenceNational = (float) $check->difference_national;
                        $isOk = abs($difference) < 0.01 && abs($differenceInter) < 0.01 && abs($differenceNational) < 0.01;
                        $badgeClass = $isOk ? 'badge-success' : 'badge-danger';
                    @endphp
                    <tr>
                        <td><strong>{{ $check->gare?->name ?? '-' }}</strong></td>
                        <td>{{ \Illuminate\Support\Str::headline($check->control_mode ?? 'direct') }}</td>
                        <td>{{ $check->operation_date?->format('d/m/Y') }}</td>
                        <td class="amount-cell">{{ number_format($check->recettes_inter_total, 0, ',', ' ') }} / {{ number_format($check->recettes_national_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->depenses_inter_total, 0, ',', ' ') }} / {{ number_format($check->depenses_national_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->expected_inter_versement, 0, ',', ' ') }} / {{ number_format($check->expected_national_versement, 0, ',', ' ') }}</td>
                        <td class="amount-cell">{{ number_format($check->versements_inter_total, 0, ',', ' ') }} / {{ number_format($check->versements_national_total, 0, ',', ' ') }}</td>
                        <td class="amount-cell">
                            <span class="badge {{ $badgeClass }}">{{ number_format($difference, 0, ',', ' ') }}</span>
                            <div class="text-muted compact-note">
                                I: {{ number_format($differenceInter, 0, ',', ' ') }} | N: {{ number_format($differenceNational, 0, ',', ' ') }}
                            </div>
                            @if($check->reviewer)
                                <div class="text-muted compact-note">
                                    {{ $statuses[$check->status] ?? \Illuminate\Support\Str::headline($check->status) }}
                                    - {{ $check->reviewer->name }}
                                    - {{ $check->reviewed_at?->format('d/m/Y H:i') }}
                                </div>
                            @endif
                        </td>
                        <td class="verification-actions">
                            @if($isOk)
                                <span class="text-muted">Aucune action</span>
                            @else
                                <form method="POST" action="{{ route('verifications.confirm', ['verification' => $check, 'module' => $module->value]) }}" class="form-inline form-inline-compact">
                                    @csrf
                                    <input type="text" name="review_note" placeholder="Note">
                                    <button class="btn btn-sm btn-outline icon-only-btn" type="submit" title="Confirmer l'ecart" aria-label="Confirmer l'ecart">
                                        <span class="icon">{!! app_icon('checklist') !!}</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('verifications.enable-adjustments', ['verification' => $check, 'module' => $module->value]) }}" class="form-inline form-inline-compact">
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
                        <td colspan="9">Aucune verification pour cette date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $checks->links('partials.pagination') }}
@endsection

