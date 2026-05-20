@extends('layouts.app')

@section('title', 'Vérification')
@section('heading', 'Module de vérification')
@section('subheading', ($module?->value ?? 'gares') === 'courrier' ? 'Contrôle du service courrier : versement = recette - dépense' : '')

@section('content')
    @php
        $cashierCoverageByCheck = $cashierCoverageByCheck ?? [];
    @endphp

    <div class="panel">
        <form method="GET" class="filters-grid verification-filters">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date début</label>
                <input type="date" name="start_date" value="{{ $startDate }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ $endDate }}">
            </div>
            <div>
                @php
                    $formatGareLabel = fn ($gare) => trim(($gare->name ?? '').(($gare->city ?? '') !== '' ? ' - '.$gare->city : ''));
                    $selectedGare = ($gares ?? collect())->firstWhere('id', (int) request('gare_id'));
                    $selectedGareLabel = $selectedGare ? $formatGareLabel($selectedGare) : '';
                @endphp
                <label>Gare</label>
                <input type="hidden" name="gare_id" value="{{ request('gare_id') }}" data-filtered-select-value>
                <input
                    type="text"
                    list="verification-gares-list"
                    value="{{ $selectedGareLabel }}"
                    placeholder="Toutes les gares"
                    data-filtered-select-label
                    autocomplete="off"
                >
                <datalist id="verification-gares-list">
                    <option value="Toutes les gares" data-value=""></option>
                    @foreach(($gares ?? collect()) as $gare)
                        <option value="{{ $formatGareLabel($gare) }}" data-value="{{ $gare->id }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                @php
                    $selectedStatusLabel = $statuses[(string) request('status')] ?? '';
                @endphp
                <label>Statut</label>
                <input type="hidden" name="status" value="{{ request('status') }}" data-filtered-select-value>
                <input
                    type="text"
                    list="verification-status-list"
                    value="{{ $selectedStatusLabel }}"
                    placeholder="Tous les statuts"
                    data-filtered-select-label
                    autocomplete="off"
                >
                <datalist id="verification-status-list">
                    <option value="Tous les statuts" data-value=""></option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $label }}" data-value="{{ $value }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="align-end">
                <button class="btn btn-primary" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Vérifier
                </button>
            </div>
        </form>
    </div>

    @if($canPurgeVerificationPeriod ?? false)
        <div class="panel">
            <form method="POST" action="{{ route('verifications.purge-period', ['module' => $module->value]) }}" class="filters-grid" onsubmit="return confirm('Supprimer les vérifications sur cette période ?');">
                @csrf
                @method('DELETE')
                <div>
                    <label>Date début</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" required>
                </div>
                <div>
                    <label>Date fin</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" required>
                </div>
                <div class="align-end">
                    <button class="btn btn-outline" type="submit" title="Supprimer" aria-label="Supprimer">
                        <span class="icon">{!! app_icon('trash') !!}</span>
                        <span class="sr-only">Supprimer</span>
                    </button>
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
                        $cashierName = trim((string) ($check->gare?->assignedCashier?->name ?? ''));
                        $isCashierVersement = ($check->gare?->versement_mode ?? 'direct') === 'cashier';
                        $isCashierVirtual = ($isSingleDayPeriod ?? false) && (bool) ($check->gare?->is_virtual ?? false) && (int) ($check->gare?->virtual_owner_user_id ?? 0) > 0;
                        $cashierCoverage = $isCashierVirtual ? ($cashierCoverageByCheck[$check->id] ?? null) : null;
                        $cashierValidatedCount = (int) ($cashierCoverage['validated_count'] ?? 0);
                        $cashierExpectedCount = (int) ($cashierCoverage['expected_count'] ?? 0);
                        $cashierRows = $cashierCoverage['rows'] ?? [];
                        $cashierDetailId = 'cashier-detail-'.$check->id;
                        $verificationUnlockPopupId = 'verification-unlock-'.$check->id;
                        $isVerificationUnlockActive = $check->modifications_enabled_until && $check->modifications_enabled_until->isFuture();
                        $isAlreadyConfirmed = $check->status === 'difference_confirmee';
                    @endphp
                    <tr>
                        <td data-label="Gare"><strong>{{ $check->gare?->name ?? '-' }}</strong></td>
                        <td data-label="Date">{{ $check->operation_date?->format('d/m/Y') }}</td>
                        <td class="amount-cell" data-label="Recettes (I/N)">
                            <span class="verification-pair">
                                <span>{{ number_format($check->recettes_inter_total, 0, '', ' ') }}</span>
                                <span class="pair-sep">/</span>
                                <span>{{ number_format($check->recettes_national_total, 0, '', ' ') }}</span>
                            </span>
                        </td>
                        <td class="amount-cell" data-label="Dépenses (I/N)">
                            <span class="verification-pair">
                                <span>{{ number_format($check->depenses_inter_total, 0, '', ' ') }}</span>
                                <span class="pair-sep">/</span>
                                <span>{{ number_format($check->depenses_national_total, 0, '', ' ') }}</span>
                            </span>
                        </td>
                        <td class="amount-cell" data-label="Attendu (I/N)">
                            <span class="verification-pair">
                                <span>{{ number_format($check->expected_inter_versement, 0, '', ' ') }}</span>
                                <span class="pair-sep">/</span>
                                <span>{{ number_format($check->expected_national_versement, 0, '', ' ') }}</span>
                            </span>

                            @if($isCashierVirtual)
                                <div class="cashier-progress-block">
                                    <button
                                        type="button"
                                        class="cashier-progress-button"
                                        data-cashier-popup-open="{{ $cashierDetailId }}"
                                        aria-label="Voir les gares du caissier : {{ $cashierValidatedCount }} gare sur {{ $cashierExpectedCount }}"
                                    >
                                        {{ $cashierValidatedCount }} gare sur {{ $cashierExpectedCount }}
                                    </button>
                                </div>

                                <div class="cashier-popup" id="{{ $cashierDetailId }}" hidden>
                                    <div class="cashier-popup__backdrop" data-cashier-popup-close></div>
                                    <div class="cashier-popup__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $cashierDetailId }}-title">
                                        <div class="cashier-popup__header">
                                            <div>
                                                <h3 id="{{ $cashierDetailId }}-title">Suivi des gares du caissier</h3>
                                                <p>{{ $cashierValidatedCount }} sur {{ $cashierExpectedCount }} gares validées</p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline" data-cashier-popup-close>Fermer</button>
                                        </div>
                                        <div class="cashier-popup__body">
                                            <table class="cashier-popup-table">
                                                <thead>
                                                    <tr>
                                                        <th>Gare</th>
                                                        <th>Manquant</th>
                                                        <th>Validé</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($cashierRows as $cashierRow)
                                                        <tr>
                                                            <td>{{ $cashierRow['gare_name'] ?? '-' }}</td>
                                                            <td>{{ !empty($cashierRow['missing']) ? 'Oui' : 'Non' }}</td>
                                                            <td>{{ !empty($cashierRow['validated']) ? 'Oui' : 'Non' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3">Aucune gare à charge.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="amount-cell" data-label="Versements (I/N)">
                            @if($isCashierVersement)
                                <span class="text-muted">{{ $cashierName !== '' ? 'Voir '.$cashierName : 'Voir nom du caissier' }}</span>
                            @else
                                <span class="verification-pair">
                                    <span>{{ number_format($check->versements_inter_total, 0, '', ' ') }}</span>
                                    <span class="pair-sep">/</span>
                                    <span>{{ number_format($check->versements_national_total, 0, '', ' ') }}</span>
                                </span>
                            @endif
                        </td>
                        <td class="amount-cell" data-label="Écart (I/N)">
                            <span class="badge {{ $badgeClass }}">{{ number_format($difference, 0, '', ' ') }}</span>
                            <div class="text-muted compact-note">
                                <span class="verification-split">I: {{ number_format($differenceInter, 0, '', ' ') }}</span>
                                <span class="verification-split">N: {{ number_format($differenceNational, 0, '', ' ') }}</span>
                            </div>
                            @if($check->reviewer)
                                <div class="text-muted compact-note verification-review-meta">
                                    <span>{{ $statuses[$check->status] ?? \Illuminate\Support\Str::headline($check->status) }}</span>
                                    <span>{{ $check->reviewer->name }}</span>
                                    <span>{{ $check->reviewed_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="verification-actions" data-label="Actions">
                            @if($isOk)
                                <span class="text-muted">Aucune action</span>
                            @else
                                <div class="cashier-approval-actions">
                                    <form method="POST" action="{{ route('verifications.confirm', ['verification' => $check, 'module' => $module->value]) }}">
                                        @csrf
                                        <button
                                            class="btn btn-sm {{ $isAlreadyConfirmed ? 'btn-outline' : 'btn-primary' }}"
                                            type="submit"
                                            @disabled($isAlreadyConfirmed)
                                        >
                                            {{ $isAlreadyConfirmed ? 'Valide' : 'Valider' }}
                                        </button>
                                    </form>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline cashier-unlock-button"
                                        data-cashier-popup-open="{{ $verificationUnlockPopupId }}"
                                        title="Deverrouiller la saisie"
                                        aria-label="Deverrouiller la saisie"
                                    >
                                        <span class="icon">{!! app_icon($isVerificationUnlockActive ? 'lock_open' : 'lock_closed') !!}</span>
                                        <span class="sr-only">Deverrouiller</span>
                                    </button>
                                </div>

                                <div class="cashier-popup cashier-popup--unlock" id="{{ $verificationUnlockPopupId }}" hidden>
                                    <div class="cashier-popup__backdrop" data-cashier-popup-close></div>
                                    <div class="cashier-popup__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $verificationUnlockPopupId }}-title">
                                        <div class="cashier-popup__header">
                                            <div>
                                                <h3 id="{{ $verificationUnlockPopupId }}-title">Deverrouillage de saisie</h3>
                                                <p>{{ $check->gare?->name ?? '-' }} · {{ $check->operation_date?->format('d/m/Y') }}</p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline" data-cashier-popup-close>Fermer</button>
                                        </div>
                                        <div class="cashier-popup__body">
                                            <form method="POST" action="{{ route('verifications.enable-adjustments', ['verification' => $check, 'module' => $module->value]) }}" class="stack-sm">
                                                @csrf
                                                <div class="unlock-controls unlock-controls--compact cashier-unlock-inline-row">
                                                    <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
                                                    <select name="unlock_unit" class="unlock-unit" required>
                                                        <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                                                        <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                                                        <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
                                                    </select>
                                                    <button class="btn btn-sm btn-primary" type="submit">Confirmer</button>
                                                    <button type="button" class="btn btn-sm btn-outline" data-cashier-popup-close>Annuler</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Aucune vérification pour cette période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $checks->links('partials.pagination') }}
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.verification-filters div').forEach(function (wrapper) {
            const hiddenInput = wrapper.querySelector('input[data-filtered-select-value]');
            const labelInput = wrapper.querySelector('input[data-filtered-select-label]');
            const listId = labelInput ? labelInput.getAttribute('list') : '';
            const datalist = listId ? document.getElementById(listId) : null;

            if (!hiddenInput || !labelInput || !datalist) {
                return;
            }

            const options = Array.from(datalist.querySelectorAll('option'));

            function syncFilteredValue() {
                const typed = (labelInput.value || '').trim().toLowerCase();
                if (!typed) {
                    hiddenInput.value = '';
                    return;
                }

                const exact = options.find(function (option) {
                    return (option.value || '').trim().toLowerCase() === typed;
                });
                if (exact) {
                    hiddenInput.value = exact.dataset.value || '';
                    return;
                }

                const partial = options.find(function (option) {
                    const optionValue = (option.value || '').trim().toLowerCase();
                    const hiddenValue = option.dataset.value || '';
                    return hiddenValue !== '' && optionValue.includes(typed);
                });
                hiddenInput.value = partial ? (partial.dataset.value || '') : '';
            }

            labelInput.addEventListener('input', syncFilteredValue);
            labelInput.addEventListener('change', syncFilteredValue);
            syncFilteredValue();
        });

        function closeCashierPopup(popup) {
            if (!popup) {
                return;
            }

            popup.hidden = true;
            const hasVisibleCashierPopup = Array.from(document.querySelectorAll('.cashier-popup')).some(function (item) {
                return !item.hidden;
            });
            const internalViewer = document.querySelector('[data-internal-viewer]');
            const hasVisibleInternalViewer = !!internalViewer && !internalViewer.hidden;

            if (!hasVisibleCashierPopup && !hasVisibleInternalViewer) {
                document.body.classList.remove('modal-open');
            }
        }

        document.querySelectorAll('[data-cashier-popup-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                const popupId = button.getAttribute('data-cashier-popup-open');
                const popup = popupId ? document.getElementById(popupId) : null;
                if (!popup) {
                    return;
                }

                popup.hidden = false;
                document.body.classList.add('modal-open');
            });
        });

        document.querySelectorAll('.cashier-popup').forEach(function (popup) {
            popup.querySelectorAll('[data-cashier-popup-close]').forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    closeCashierPopup(popup);
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            const openedPopups = Array.from(document.querySelectorAll('.cashier-popup')).filter(function (popup) {
                return !popup.hidden;
            });

            if (openedPopups.length === 0) {
                return;
            }

            closeCashierPopup(openedPopups[openedPopups.length - 1]);
        });
    </script>
@endpush
