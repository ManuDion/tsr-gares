@extends('layouts.app')

@section('title', 'Receptions caissier')
@section('heading', 'Validation des sommes recues')
@section('subheading', 'Le caissier valide les ecritures des gares avant alimentation de son compte')

@section('content')
    @php
        $collectsInter = $collectsInter ?? true;
        $collectsNational = $collectsNational ?? true;
        $rowsByDate = collect($rows ?? [])->groupBy('operation_date');
    @endphp

    <div class="panel">
        <form method="GET" class="filters-grid">
            <input type="hidden" name="module" value="{{ $module->value }}">
            <div>
                <label>Date debut</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}">
            </div>
            <div>
                <label>Date fin</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}">
            </div>
            <div class="align-end">
                <button class="btn btn-outline" type="submit">Actualiser</button>
            </div>
        </form>
    </div>

    @if($rowsByDate->isNotEmpty())
        <div class="panel">
            <div class="filters-grid">
                <div>
                    <label>Validation par date</label>
                    <small>Valide toutes les lignes en attente pour la date choisie.</small>
                </div>
                <div class="align-end gap-sm">
                    @foreach($rowsByDate as $operationDate => $dateRows)
                        @php
                            $pendingCount = $dateRows->filter(fn ($dateRow) => ! (bool) ($dateRow['is_verified'] ?? false))->count();
                            $isDateFullyValidated = $pendingCount === 0;
                        @endphp
                        <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}">
                            @csrf
                            <input type="hidden" name="mode" value="validate_date">
                            <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                            <button class="btn {{ $isDateFullyValidated ? 'btn-outline' : 'btn-primary' }}" type="submit" @disabled($isDateFullyValidated)>
                                {{ $isDateFullyValidated ? 'Valide' : 'Valider' }} {{ \Carbon\Carbon::parse($operationDate)->format('d/m/Y') }} ({{ $pendingCount }}/{{ $dateRows->count() }})
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="table-wrapper cashier-receipts-table-wrapper">
        <table class="cashier-receipts-table">
            <thead>
                <tr>
                    <th class="col-gare">Gare</th>
                    <th class="col-date">Date</th>
                    <th>Recette Inter</th>
                    <th>Recette Nationale</th>
                    <th class="col-depenses">Depenses</th>
                    <th>Total attendu</th>
                    <th class="col-phone"><span class="th-two-lines">Numero de<br>telephone</span></th>
                    <th class="col-approval"><span class="th-two-lines">Approbation<br>caissier</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $gare = $row['gare'];
                        $operationDate = (string) ($row['operation_date'] ?? $startDate);
                        $expected = $row['expected'];
                        $confirmation = $row['confirmation'];
                        $isLocked = (bool) ($row['is_locked'] ?? false);
                        $validateInter = $collectsInter ? (int) round((float) ($confirmation->received_inter_total ?? $expected['expected_inter']), 0) : 0;
                        $validateNational = $collectsNational ? (int) round((float) ($confirmation->received_national_total ?? $expected['expected_national']), 0) : 0;
                        $validateTotal = $validateInter + $validateNational;
                        $unlockPopupId = 'cashier-unlock-'.$gare->id.'-'.$loop->index;
                        $isAlreadyValidated = (bool) ($row['is_verified'] ?? false);
                    @endphp
                    <tr>
                        <td class="col-gare">{{ $gare->name }}</td>
                        <td class="col-date">{{ \Carbon\Carbon::parse($operationDate)->format('d/m/Y') }}</td>
                        <td>{{ number_format($expected['recette_inter'], 0, '', ' ') }}</td>
                        <td>{{ number_format($expected['recette_national'], 0, '', ' ') }}</td>
                        <td class="col-depenses"><strong>{{ number_format($expected['depense_total'], 0, '', ' ') }}</strong></td>
                        <td><strong>{{ number_format($expected['expected_total'], 0, '', ' ') }}</strong></td>
                        <td class="col-phone">{{ $row['phone'] ?: '-' }}</td>
                        <td class="col-approval">
                            <div class="cashier-approval-actions">
                                <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}" class="stack-sm">
                                    @csrf
                                    <input type="hidden" name="mode" value="validate">
                                    <input type="hidden" name="gare_id" value="{{ $gare->id }}">
                                    <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                                    <input type="hidden" name="received_inter_total" value="{{ $validateInter }}">
                                    <input type="hidden" name="received_national_total" value="{{ $validateNational }}">
                                    <input type="hidden" name="received_total" value="{{ $validateTotal }}">
                                    <button class="btn btn-sm {{ $isAlreadyValidated ? 'btn-outline' : 'btn-primary' }}" type="submit" @disabled($isAlreadyValidated)>
                                        {{ $isAlreadyValidated ? 'Valide' : 'Valider' }}
                                    </button>
                                </form>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline cashier-unlock-button"
                                    data-cashier-unlock-open="{{ $unlockPopupId }}"
                                    title="Deverrouiller la saisie"
                                >
                                    <span class="icon">{!! app_icon($isLocked ? 'lock_closed' : 'lock_open') !!}</span>
                                    <span class="sr-only">Deverrouiller</span>
                                </button>
                            </div>

                            <div class="cashier-popup cashier-popup--unlock" id="{{ $unlockPopupId }}" hidden>
                                <div class="cashier-popup__backdrop" data-cashier-unlock-close></div>
                                <div class="cashier-popup__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $unlockPopupId }}-title">
                                    <div class="cashier-popup__header">
                                        <div>
                                            <h3 id="{{ $unlockPopupId }}-title">Deverrouillage de saisie</h3>
                                            <p>{{ $gare->name }} · {{ \Carbon\Carbon::parse($operationDate)->format('d/m/Y') }}</p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline" data-cashier-unlock-close>Fermer</button>
                                    </div>
                                    <div class="cashier-popup__body">
                                        <form method="POST" action="{{ route('cashier-receipts.store', ['module' => $module->value]) }}" class="stack-sm">
                                            @csrf
                                            <input type="hidden" name="gare_id" value="{{ $gare->id }}">
                                            <input type="hidden" name="operation_date" value="{{ $operationDate }}">
                                            <input type="hidden" name="received_inter_total" value="{{ $validateInter }}">
                                            <input type="hidden" name="received_national_total" value="{{ $validateNational }}">
                                            <input type="hidden" name="received_total" value="{{ $validateTotal }}">

                                            <div class="unlock-controls unlock-controls--compact cashier-unlock-inline-row">
                                                <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
                                                <select name="unlock_unit" class="unlock-unit" required>
                                                    <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                                                    <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                                                    <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
                                                </select>
                                                <button class="btn btn-sm btn-primary" type="submit" name="mode" value="unlock">Confirmer</button>
                                                <button type="button" class="btn btn-sm btn-outline" data-cashier-unlock-close>Annuler</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Aucune ligne a afficher pour cette periode.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            function closePopup(popup) {
                if (!popup) {
                    return;
                }

                popup.hidden = true;
                const hasVisiblePopup = Array.from(document.querySelectorAll('.cashier-popup')).some(function (item) {
                    return !item.hidden;
                });

                if (!hasVisiblePopup) {
                    document.body.classList.remove('modal-open');
                }
            }

            document.querySelectorAll('[data-cashier-unlock-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const popupId = button.getAttribute('data-cashier-unlock-open');
                    const popup = popupId ? document.getElementById(popupId) : null;
                    if (!popup) {
                        return;
                    }

                    popup.hidden = false;
                    document.body.classList.add('modal-open');
                });
            });

            document.querySelectorAll('.cashier-popup').forEach(function (popup) {
                popup.querySelectorAll('[data-cashier-unlock-close]').forEach(function (closeButton) {
                    closeButton.addEventListener('click', function () {
                        closePopup(popup);
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

                closePopup(openedPopups[openedPopups.length - 1]);
            });
        })();
    </script>
@endpush







