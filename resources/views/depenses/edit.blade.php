@extends('layouts.app')

@section('title', 'Modifier dépense')
@section('heading', ($module?->value ?? 'gares') === 'courrier' ? 'Modifier une dépense courrier' : 'Modifier une dépense')
@section('subheading', 'Historique et contrôle de la règle 48 heures')

@section('actions')
    @if(auth()->user()->canUnlockFinancialScope($depense->service_scope))
        @php
            $isUnlockActive = $depense->force_unlocked_until && $depense->force_unlocked_until->isFuture();
            $unlockPopupId = 'depense-unlock-'.$depense->id;
        @endphp
        <div class="cashier-approval-actions">
            <button class="btn btn-sm btn-primary" type="submit" form="depense-update-form">Valider</button>
            <button
                type="button"
                class="btn btn-sm btn-outline cashier-unlock-button"
                data-cashier-unlock-open="{{ $unlockPopupId }}"
                title="Deverrouiller la saisie"
            >
                <span class="icon">{!! app_icon($isUnlockActive ? 'lock_open' : 'lock_closed') !!}</span>
                <span class="sr-only">Deverrouiller</span>
            </button>
        </div>

        <div class="cashier-popup cashier-popup--unlock" id="{{ $unlockPopupId }}" hidden>
            <div class="cashier-popup__backdrop" data-cashier-unlock-close></div>
            <div class="cashier-popup__panel" role="dialog" aria-modal="true" aria-labelledby="{{ $unlockPopupId }}-title">
                <div class="cashier-popup__header">
                    <div>
                        <h3 id="{{ $unlockPopupId }}-title">Deverrouillage de saisie</h3>
                        <p>{{ $depense->gare?->name ?? '-' }} · {{ $depense->operation_date?->format('d/m/Y') }}</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" data-cashier-unlock-close>Fermer</button>
                </div>
                <div class="cashier-popup__body">
                    <form method="POST" action="{{ route('depenses.unlock', ['depense' => $depense, 'module' => $module->value]) }}" class="stack-sm">
                        @csrf
                        <input type="hidden" name="unlock_reason" value="Deverrouillage manuel par superviseur">
                        <div class="unlock-controls unlock-controls--compact cashier-unlock-inline-row">
                            <input type="number" name="unlock_duration" min="1" step="1" value="{{ old('unlock_duration', 24) }}" class="unlock-duration" required>
                            <select name="unlock_unit" class="unlock-unit" required>
                                <option value="minutes" @selected(old('unlock_unit') === 'minutes')>Minutes</option>
                                <option value="hours" @selected(old('unlock_unit', 'hours') === 'hours')>Heures</option>
                                <option value="days" @selected(old('unlock_unit') === 'days')>Jours</option>
                            </select>
                            <button class="btn btn-sm btn-primary" type="submit">Confirmer</button>
                            <button type="button" class="btn btn-sm btn-outline" data-cashier-unlock-close>Annuler</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('content')
    <div class="grid-2">
        <div class="panel">
            <form id="depense-update-form" method="POST" action="{{ route('depenses.update', ['depense' => $depense, 'module' => $module->value]) }}" enctype="multipart/form-data" class="stack-md">
                @csrf
                @method('PUT')
                <input type="hidden" name="module" value="{{ $module->value }}">
                @include('depenses._form', ['depense' => $depense])
                <div class="form-actions">
                    <a class="btn btn-outline" href="{{ route('depenses.index', ['module' => $module->value]) }}">Retour</a>
                    <button class="btn btn-primary" type="submit">Valider</button>
                </div>
            </form>

            @if($depense->justificatives->isNotEmpty())
                <div class="doc-links doc-links-top">
                    @foreach($depense->justificatives as $piece)
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Justificatif depense' }}" onclick="return window.openInternalFileViewer(this);" title="Voir" aria-label="Voir">
                            <span class="icon">{!! app_icon('eye') !!}</span>
                            <span class="sr-only">Voir</span>
                        </a>
                        @if(auth()->user()->hasGlobalVisibility())
                            <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}" title="Télécharger" aria-label="Télécharger">
                                            <span class="icon">{!! app_icon('download') !!}</span>
                                            <span class="sr-only">Télécharger</span>
                                        </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Historique des modifications</h2>
            <div class="timeline">
                @forelse($depense->histories as $history)
                    <div class="timeline-item timeline-item-detailed">
                        <strong>{{ $history->modifier->name ?? 'Système' }}</strong>
                        <small>{{ $history->created_at?->format('d/m/Y H:i') }}</small>
                        <p>{{ $history->comment ?: 'Modification' }}</p>
                        <div class="history-diff-grid">
                            <div>
                                <h3>Avant</h3>
                                <ul class="change-list">
                                    @foreach($history->before ?? [] as $key => $value)
                                        <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ $value === null || $value === '' ? '—' : $value }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <h3>Après</h3>
                                <ul class="change-list">
                                    @foreach($history->after ?? [] as $key => $value)
                                        <li><strong>{{ \Illuminate\Support\Str::headline($key) }} :</strong> {{ $value === null || $value === '' ? '—' : $value }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @empty
                    <p>Aucune modification enregistrée.</p>
                @endforelse
            </div>
        </div>
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
