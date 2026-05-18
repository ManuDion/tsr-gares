@php
    $maxSizeKb = $maxSizeKb ?? (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120);
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $versement?->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' - '.$selectedGare->city) : ($defaultGareLabel ?? null);
    $existingPieces = isset($versement) ? $versement->justificatives->sortByDesc('id') : collect();
    $selectedAccountType = old('account_type', $versement?->account_type ?? 'national');
    $scope = (($module?->value ?? 'gares') === 'courrier') ? 'courrier' : 'gares';
    $isCashierScope = auth()->user()->canActAsCashierForScope($scope);
    $resolvedVirtualGare = $virtualGare ?? ((isset($versement) && $versement->gare && $versement->gare->is_virtual) ? $versement->gare : null);
    $garesActivityModes = collect($gares)->mapWithKeys(fn ($gare) => [(string) $gare->id => $gare->activity_mode ?? 'mixed'])->all();
    $bankRoutingWindows = collect($bankRoutingWindows ?? [])->map(function ($row) {
        return [
            'start_date' => optional($row->start_date)->toDateString(),
            'end_date' => optional($row->end_date)->toDateString(),
            'forced_account_type' => $row->forced_account_type,
            'gare_ids' => collect($row->gares ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    })->values()->all();
    $serverForcedAccountType = $forcedAccountType ?? null;
    if ($serverForcedAccountType === 'inter' || $serverForcedAccountType === 'national') {
        $selectedAccountType = $serverForcedAccountType;
    }
    $chefActivityMode = $isCashierScope
        ? ($resolvedVirtualGare?->activity_mode ?? 'mixed')
        : (auth()->user()->primaryGare?->activity_mode ?? 'mixed');
    $serverInterOnly = $chefActivityMode === 'inter_only';
    $serverNationalOnly = $chefActivityMode === 'national_only';
@endphp

<div
    class="form-grid"
    data-account-form
    data-gare-activity-modes='@json($garesActivityModes)'
    data-chef-activity-mode="{{ $chefActivityMode }}"
    data-bank-routing-windows='@json($bankRoutingWindows)'
>
    @if($isCashierScope)
        <div>
            <label>Gare de traitement</label>
            <input type="hidden" name="gare_id" value="{{ old('gare_id', $resolvedVirtualGare?->id) }}">
            <input type="text" value="{{ $resolvedVirtualGare?->name ?? 'Compte caissier' }}" disabled>
            <small>Affectee automatiquement a votre caisse.</small>
        </div>
    @elseif(!auth()->user()->canActAsChefForScope($scope) || auth()->user()->canUseMultiGareEntry())
        <x-gare-picker
            :gares="$gares"
            datalistId="versement-gares"
            :selectedGareLabel="$selectedGareLabel"
            :selectedGareId="old('gare_id', $versement?->gare_id ?? null)"
        />
    @else
        <div>
            <label>Gare affectee</label>
            <input type="hidden" name="gare_id" value="{{ auth()->user()->gare_id }}">
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
            <small>Issue de votre connexion.</small>
        </div>
    @endif

    <div>
        <label>Date operation</label>
        <input type="date" name="operation_date" value="{{ old('operation_date', $versement?->operation_date?->toDateString() ?? now()->toDateString()) }}" required>
        @if($isCashierScope && $serverForcedAccountType)
            <small>Regle active: ce versement caissier est verrouille sur {{ $serverForcedAccountType === 'inter' ? 'Ecobank' : 'Coris Bank' }}.</small>
        @endif
    </div>

    <div>
        <label>Date de la recette</label>
        <input type="date" name="receipt_date" value="{{ old('receipt_date', $versement?->receipt_date?->toDateString() ?? '') }}" required>
    </div>

    <div>
        <label>Compte de versement</label>
        <select name="account_type" data-account-type required>
            <option value="inter" data-account-inter-option @selected($selectedAccountType === 'inter') @disabled($serverForcedAccountType === 'national' || ($serverNationalOnly && ! $serverForcedAccountType))>Inter (Ecobank)</option>
            <option value="national" data-account-national-option @selected($selectedAccountType === 'national') @disabled($serverForcedAccountType === 'inter' || ($serverInterOnly && ! $serverForcedAccountType))>National (Coris Bank)</option>
        </select>
        <small data-inter-only-account-hint @if(!($serverInterOnly && ! $serverForcedAccountType)) hidden @endif>Gare inter uniquement: seul le compte inter est autorise.</small>
        <small data-national-only-account-hint @if(!($serverNationalOnly && ! $serverForcedAccountType)) hidden @endif>Gare national uniquement: seul le compte national est autorise.</small>
        <small data-forced-account-hint @if(!$serverForcedAccountType) hidden @endif>
            Banque imposee par parametrage superviseur pour cette date:
            {{ $serverForcedAccountType === 'inter' ? 'Ecobank' : 'Coris Bank' }}.
        </small>
    </div>

    <div>
        <label>Montant</label>
        @php
            $amountValue = old('amount', $versement?->amount ?? '');
            $amountValue = is_numeric($amountValue) ? (int) round((float) $amountValue, 0) : $amountValue;
        @endphp
        <input type="text" inputmode="numeric" pattern="[0-9]*" name="amount" value="{{ (string) $amountValue === '0' ? '' : $amountValue }}" data-amount-field data-clear-zero required>
    </div>

    <div>
        <label>Banque</label>
        <input type="text" name="bank_name" data-bank-name value="{{ old('bank_name', $versement?->bank_name ?? '') }}">
        <small>Inter = Ecobank, National = Coris Bank.</small>
    </div>

    <div>
        <label>Reference (nom de l'agence)</label>
        <input type="text" name="reference" value="{{ old('reference', $versement?->reference ?? '') }}">
    </div>

    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $versement?->description ?? '') }}</textarea>
    </div>

    <div>
        <label>Nom du bordereau</label>
        <input type="text" name="bordereau_name" value="{{ old('bordereau_name') }}" placeholder="Ex. Bordereau Ecobank 15-08-2026">
    </div>

    <div>
        <label>Bordereaux justificatifs {{ isset($versement) ? '(optionnels en modification)' : '(au moins un obligatoire)' }} (max {{ $maxSizeKb }} Ko par fichier)</label>
        <input type="file" name="bordereaux[]" accept="image/*,.heic,.heif,.webp,.jpg,.jpeg,.png,.pdf,application/pdf" multiple @required(!isset($versement))>
        <small>Vous pouvez joindre jusqu'a 10 photos/fichiers (Android, iPhone, galerie ou camera).</small>
    </div>

    @if($existingPieces->isNotEmpty())
        <div class="col-span-2">
            @foreach($existingPieces as $piece)
                <div class="doc-links">
                    <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $piece) }}" data-internal-file-preview data-file-title="{{ $piece->original_name ?? 'Bordereau actuel' }}" onclick="return window.openInternalFileViewer(this);">
                        <span class="icon">{!! app_icon('eye') !!}</span> Lire {{ $piece->original_name ?? 'le bordereau' }}
                    </a>
                    @if(auth()->user()->hasGlobalVisibility())
                        <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $piece) }}">
                            <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if(isset($versement))
        <div class="col-span-2">
            <label>Commentaire d'historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Motif de la modification">
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-account-form]').forEach(function (wrapper) {
                const accountSelect = wrapper.querySelector('[data-account-type]');
                const bankInput = wrapper.querySelector('[data-bank-name]');
                const amountInput = wrapper.querySelector('[data-amount-field]');
                const interOnlyHint = wrapper.querySelector('[data-inter-only-account-hint]');
                const nationalOnlyHint = wrapper.querySelector('[data-national-only-account-hint]');
                const forcedAccountHint = wrapper.querySelector('[data-forced-account-hint]');
                const interOption = wrapper.querySelector('[data-account-inter-option]');
                const nationalOption = wrapper.querySelector('[data-account-national-option]');
                const gareActivityModes = JSON.parse(wrapper.getAttribute('data-gare-activity-modes') || '{}');
                const chefActivityMode = wrapper.getAttribute('data-chef-activity-mode') || 'mixed';
                const routingWindows = JSON.parse(wrapper.getAttribute('data-bank-routing-windows') || '[]');
                const operationDateInput = wrapper.querySelector('input[name="operation_date"]');

                function selectedGareId() {
                    const hiddenInput = wrapper.querySelector('input[data-gare-id]') || wrapper.querySelector('input[name="gare_id"]');
                    return hiddenInput ? String(hiddenInput.value || '') : '';
                }

                function isInterOnly() {
                    const hasPicker = !!wrapper.querySelector('input[data-gare-id]');
                    if (!hasPicker) {
                        return chefActivityMode === 'inter_only';
                    }

                    const id = selectedGareId();
                    if (!id || !gareActivityModes[id]) {
                        return false;
                    }

                    return gareActivityModes[id] === 'inter_only';
                }

                function isNationalOnly() {
                    const hasPicker = !!wrapper.querySelector('input[data-gare-id]');
                    if (!hasPicker) {
                        return chefActivityMode === 'national_only';
                    }

                    const id = selectedGareId();
                    if (!id || !gareActivityModes[id]) {
                        return false;
                    }

                    return gareActivityModes[id] === 'national_only';
                }

                function dateMatchesWindow(dateStr, window) {
                    if (!dateStr || !window || !window.start_date) return false;
                    if (dateStr < window.start_date) return false;
                    if (window.end_date && dateStr > window.end_date) return false;
                    return true;
                }

                function forcedAccountForDate() {
                    const value = operationDateInput ? operationDateInput.value : '';
                    if (!value) return null;
                    const gareId = selectedGareId();
                    const match = routingWindows.find(function (window) {
                        if (!dateMatchesWindow(value, window)) {
                            return false;
                        }

                        const gares = Array.isArray(window.gare_ids) ? window.gare_ids : [];
                        if (gares.length === 0) {
                            return true;
                        }

                        if (!gareId) {
                            return false;
                        }

                        return gares.includes(Number(gareId));
                    });
                    return match ? match.forced_account_type : null;
                }

                function syncAccountRestrictions() {
                    if (!accountSelect) return;
                    const forcedAccount = forcedAccountForDate();
                    const interOnly = isInterOnly();
                    const nationalOnly = isNationalOnly();

                    if (interOption) {
                        interOption.disabled = forcedAccount ? forcedAccount !== 'inter' : nationalOnly;
                    }
                    if (nationalOption) {
                        nationalOption.disabled = forcedAccount ? forcedAccount !== 'national' : interOnly;
                    }

                    if (forcedAccount) {
                        accountSelect.value = forcedAccount;
                    } else if (interOnly) {
                        accountSelect.value = 'inter';
                    } else if (nationalOnly) {
                        accountSelect.value = 'national';
                    }

                    if (interOnlyHint) {
                        interOnlyHint.hidden = !interOnly || !!forcedAccount || nationalOnly;
                    }
                    if (nationalOnlyHint) {
                        nationalOnlyHint.hidden = !nationalOnly || !!forcedAccount || interOnly;
                    }
                    if (forcedAccountHint) {
                        forcedAccountHint.hidden = !forcedAccount;
                    }
                }

                function expectedBank() {
                    return accountSelect && accountSelect.value === 'inter' ? 'Ecobank' : 'Coris Bank';
                }

                function syncBankName() {
                    if (!bankInput) return;
                    if (!bankInput.value || bankInput.value === 'Ecobank' || bankInput.value === 'Coris Bank') {
                        bankInput.value = expectedBank();
                    }
                }

                function sanitizeIntegerField(input) {
                    if (!input) return;
                    const raw = String(input.value || '');
                    const digits = raw.replace(/[^\d]/g, '');
                    if (digits === '') {
                        input.value = '';
                        return;
                    }
                    input.value = String(parseInt(digits, 10));
                }

                if (accountSelect) {
                    accountSelect.addEventListener('change', syncBankName);
                }
                if (operationDateInput) {
                    operationDateInput.addEventListener('change', function () {
                        syncAccountRestrictions();
                        syncBankName();
                    });
                }
                const gareLabelInput = wrapper.querySelector('input[data-gare-label]');
                const gareHiddenInput = wrapper.querySelector('input[data-gare-id]');
                if (gareLabelInput) {
                    gareLabelInput.addEventListener('change', function () {
                        window.setTimeout(function () {
                            syncAccountRestrictions();
                            syncBankName();
                        }, 0);
                    });
                    gareLabelInput.addEventListener('input', function () {
                        window.setTimeout(function () {
                            syncAccountRestrictions();
                            syncBankName();
                        }, 0);
                    });
                }
                if (gareHiddenInput) {
                    gareHiddenInput.addEventListener('change', function () {
                        syncAccountRestrictions();
                        syncBankName();
                    });
                }

                if (amountInput) {
                    amountInput.addEventListener('input', function () {
                        sanitizeIntegerField(amountInput);
                    });
                    amountInput.addEventListener('focus', function () {
                        if ((amountInput.value || '').trim() === '0') {
                            amountInput.value = '';
                        }
                    });
                    amountInput.addEventListener('blur', function () {
                        sanitizeIntegerField(amountInput);
                    });
                }
                syncAccountRestrictions();
                syncBankName();
            });
        </script>
    @endpush
@endonce
