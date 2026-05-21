@extends('layouts.app')

@section('title', 'Écritures manquantes')
@section('heading', 'Fiche des écritures manquantes')

@section('actions')
    <a class="btn btn-outline" href="{{ route('verifications.index', ['module' => $module->value, 'start_date' => $startDate, 'end_date' => $endDate]) }}">
        Retour vérification
    </a>
@endsection

@section('content')
    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date début</label>
                <input type="date" name="start_date" value="{{ $startDate }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ $endDate }}">
            </div>
            <div class="align-end gap-sm">
                <button class="btn btn-outline" type="submit">
                    <span class="icon">{!! app_icon('filter') !!}</span> Filtrer
                </button>
                <a class="btn btn-primary" href="{{ route('verifications.missing-entries.pdf', ['module' => $module->value, 'start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank">
                    Exporter PDF
                </a>
            </div>
        </form>
    </div>

    @php
        $rowsCount = $rows->count();
        $alertCount = $rows->filter(function (array $row) {
            $coverage = $row['cashier_coverage'] ?? null;
            $isCashierVirtual = (bool) ($row['is_cashier_virtual_gare'] ?? false) && is_array($coverage);

            if ($isCashierVirtual) {
                $validated = (int) ($coverage['validated_count'] ?? 0);
                $expected = (int) ($coverage['expected_count'] ?? 0);

                return $expected > 0 && $validated < $expected;
            }

            return (bool) ($row['recette_missing'] ?? false)
                || (bool) ($row['depense_missing'] ?? false)
                || ((bool) ($row['versement_coris_applicable'] ?? true) && (bool) ($row['versement_coris_missing'] ?? false))
                || ((bool) ($row['versement_ecobank_applicable'] ?? true) && (bool) ($row['versement_ecobank_missing'] ?? false));
        })->count();
        $okCount = max($rowsCount - $alertCount, 0);
    @endphp

    <div class="missing-entries-summary" aria-label="Résumé des résultats">
        <span class="summary-chip">Lignes: <strong>{{ $rowsCount }}</strong></span>
        <span class="summary-chip summary-chip--ok">OK: <strong>{{ $okCount }}</strong></span>
        <span class="summary-chip summary-chip--alert">À corriger: <strong>{{ $alertCount }}</strong></span>
    </div>

    <div class="text-muted compact-note">Période : {{ $periodLabel }}</div>

    <div class="table-wrapper missing-entries-table">
        <table class="missing-entries-grid">
            <thead>
                <tr>
                    <th class="col-gare">Gare</th>
                    <th class="col-date">Date</th>
                    <th class="col-status">Recette</th>
                    <th class="col-status col-depense">Dépense</th>
                    <th class="col-status">Versement Coris</th>
                    <th class="col-status">Versement Ecobank</th>
                    <th class="col-phone">Numéro de téléphone</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $coverage = $row['cashier_coverage'] ?? null;
                        $isCashierVirtual = (bool) ($row['is_cashier_virtual_gare'] ?? false) && is_array($coverage);
                        $validatedCount = (int) ($coverage['validated_count'] ?? 0);
                        $expectedCount = (int) ($coverage['expected_count'] ?? 0);
                        $isFullyValidated = $expectedCount === 0 || $validatedCount === $expectedCount;
                        $coveragePopupId = 'missing-coverage-'.$loop->index.'-'.md5(($row['gare'] ?? '').($row['operation_date'] ?? ''));
                        $isAlertRow = $isCashierVirtual
                            ? (! $isFullyValidated)
                            : (
                                (bool) ($row['recette_missing'] ?? false)
                                || (bool) ($row['depense_missing'] ?? false)
                                || ((bool) ($row['versement_coris_applicable'] ?? true) && (bool) ($row['versement_coris_missing'] ?? false))
                                || ((bool) ($row['versement_ecobank_applicable'] ?? true) && (bool) ($row['versement_ecobank_missing'] ?? false))
                            );
                    @endphp
                    <tr class="{{ $isAlertRow ? 'row-alert' : 'row-ok' }}">
                        <td class="col-gare">{{ $row['gare'] }}</td>
                        <td class="col-date">{{ \Carbon\Carbon::parse($row['operation_date'])->format('d/m/Y') }}</td>
                        <td class="col-status">
                            @if($isCashierVirtual)
                                <span class="badge badge-nowrap {{ $isFullyValidated ? 'badge-success' : 'badge-danger' }}">OK</span>
                                <div class="cashier-progress-block">
                                    <button type="button" class="cashier-progress-button" data-cashier-popup-open="{{ $coveragePopupId }}">
                                        {{ $validatedCount }} sur {{ $expectedCount }}
                                    </button>
                                </div>
                            @else
                                <span class="badge badge-nowrap {{ $row['recette_missing'] ? 'badge-danger' : 'badge-success' }}">
                                    {{ $row['recette_missing'] ? 'Manquant' : 'OK' }}
                                </span>
                            @endif
                        </td>
                        <td class="col-status">
                            @if($isCashierVirtual)
                                <span class="badge badge-nowrap {{ $isFullyValidated ? 'badge-success' : 'badge-danger' }}">OK</span>
                                <div class="cashier-progress-block">
                                    <button type="button" class="cashier-progress-button" data-cashier-popup-open="{{ $coveragePopupId }}">
                                        {{ $validatedCount }} sur {{ $expectedCount }}
                                    </button>
                                </div>
                            @else
                                <span class="badge badge-nowrap {{ $row['depense_missing'] ? 'badge-danger' : 'badge-success' }}">
                                    {{ $row['depense_missing'] ? 'Manquant' : 'OK' }}
                                </span>
                            @endif
                        </td>
                        <td class="col-status {{ $row['is_cashier_managed'] ? 'cashier-missing-cell' : '' }}">
                            @if($row['is_cashier_managed'])
                                <span class="text-muted">Confie au caissier : {{ $row['cashier_name'] ?? '-' }}</span>
                            @elseif(! ($row['versement_coris_applicable'] ?? true))
                                &nbsp;
                            @elseif($row['versement_coris_missing'])
                                <span class="badge badge-nowrap badge-danger">Manquant</span>
                            @else
                                <span class="badge badge-nowrap badge-success">OK</span>
                            @endif
                        </td>
                        <td class="col-status {{ $row['is_cashier_managed'] ? 'cashier-missing-cell' : '' }}">
                            @if($row['is_cashier_managed'])
                                <span class="text-muted">Confie au caissier : {{ $row['cashier_name'] ?? '-' }}</span>
                            @elseif(! ($row['versement_ecobank_applicable'] ?? true))
                                &nbsp;
                            @elseif($row['versement_ecobank_missing'])
                                <span class="badge badge-nowrap badge-danger">Manquant</span>
                            @else
                                <span class="badge badge-nowrap badge-success">OK</span>
                            @endif
                        </td>
                        <td class="col-phone">{{ $row['phone'] ?: '-' }}</td>
                    </tr>
                    @if($isCashierVirtual)
                        <tr class="cashier-popup-host" aria-hidden="true">
                            <td colspan="7">
                                <div class="cashier-popup" id="{{ $coveragePopupId }}" hidden>
                                    <div class="cashier-popup__backdrop" data-cashier-popup-close></div>
                                    <div class="cashier-popup__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $coveragePopupId }}-title">
                                        <div class="cashier-popup__header">
                                            <div>
                                                <h3 id="{{ $coveragePopupId }}-title">Suivi des gares du caissier</h3>
                                                <p>{{ $validatedCount }} sur {{ $expectedCount }} gares validees</p>
                                                <p class="text-muted">Statut affiche sur la periode : {{ $periodLabel }}</p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline" data-cashier-popup-close>Fermer</button>
                                        </div>
                                        <div class="cashier-popup__body">
                                            <table class="cashier-popup-table">
                                                <thead>
                                                    <tr>
                                                        <th>Gare</th>
                                                        <th>Statut</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse(($coverage['rows'] ?? []) as $coverageRow)
                                                        <tr>
                                                            <td>{{ $coverageRow['gare_name'] ?? '-' }}</td>
                                                            <td>{{ $coverageRow['status'] ?? '-' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="2">Aucune gare a charge.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7">Aucune écriture manquante sur cette période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const POPUP_MARGIN = 12;
            const POPUP_OFFSET = 10;
            const popupAnchors = new WeakMap();

            function moveCashierPopupsToBody() {
                document.querySelectorAll('.cashier-popup').forEach(function (popup) {
                    if (popup.parentElement !== document.body) {
                        document.body.appendChild(popup);
                    }
                });
            }

            function positionCashierPopup(popup, anchorButton) {
                if (!popup || popup.hidden) {
                    return;
                }

                const panel = popup.querySelector('.cashier-popup__panel');
                const anchor = anchorButton || popupAnchors.get(popup);
                if (!panel || !anchor) {
                    popup.classList.remove('is-anchored');
                    return;
                }

                popup.classList.add('is-anchored');

                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const anchorRect = anchor.getBoundingClientRect();

                panel.style.maxHeight = Math.max(220, viewportHeight - (POPUP_MARGIN * 2)) + 'px';

                const panelRect = panel.getBoundingClientRect();
                const panelWidth = Math.min(panelRect.width || 680, viewportWidth - (POPUP_MARGIN * 2));
                const panelHeight = Math.min(panelRect.height || 360, viewportHeight - (POPUP_MARGIN * 2));

                let left = anchorRect.left;
                if (left + panelWidth > viewportWidth - POPUP_MARGIN) {
                    left = viewportWidth - POPUP_MARGIN - panelWidth;
                }
                if (left < POPUP_MARGIN) {
                    left = POPUP_MARGIN;
                }

                let top = anchorRect.bottom + POPUP_OFFSET;
                if (top + panelHeight > viewportHeight - POPUP_MARGIN) {
                    top = anchorRect.top - panelHeight - POPUP_OFFSET;
                }
                if (top < POPUP_MARGIN) {
                    top = POPUP_MARGIN;
                }

                popup.style.setProperty('--cashier-popup-left', Math.round(left) + 'px');
                popup.style.setProperty('--cashier-popup-top', Math.round(top) + 'px');
            }

            function closeCashierPopup(popup) {
                if (!popup) {
                    return;
                }

                popup.hidden = true;
                popup.classList.remove('is-anchored');
                const hasVisibleCashierPopup = Array.from(document.querySelectorAll('.cashier-popup')).some(function (item) {
                    return !item.hidden;
                });

                if (!hasVisibleCashierPopup) {
                    document.body.classList.remove('modal-open');
                }
            }

            moveCashierPopupsToBody();

            document.querySelectorAll('[data-cashier-popup-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const popupId = button.getAttribute('data-cashier-popup-open');
                    const popup = popupId ? document.getElementById(popupId) : null;
                    if (!popup) {
                        return;
                    }

                    popup.hidden = false;
                    document.body.classList.add('modal-open');
                    popupAnchors.set(popup, button);
                    positionCashierPopup(popup, button);
                    window.requestAnimationFrame(function () {
                        positionCashierPopup(popup, button);
                    });
                });
            });

            document.querySelectorAll('.cashier-popup').forEach(function (popup) {
                popup.querySelectorAll('[data-cashier-popup-close]').forEach(function (closeButton) {
                    closeButton.addEventListener('click', function () {
                        closeCashierPopup(popup);
                    });
                });
            });

            function repositionOpenedCashierPopups() {
                document.querySelectorAll('.cashier-popup').forEach(function (popup) {
                    if (!popup.hidden) {
                        positionCashierPopup(popup);
                    }
                });
            }

            window.addEventListener('resize', repositionOpenedCashierPopups);
            window.addEventListener('scroll', repositionOpenedCashierPopups, true);

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
        })();
    </script>
@endpush

