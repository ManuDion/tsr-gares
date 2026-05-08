@php
    $maxSizeKb = $maxSizeKb ?? (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120);
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $versement?->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' - '.$selectedGare->city) : ($defaultGareLabel ?? null);
    $latestPiece = isset($versement) ? $versement?->justificatives?->sortByDesc('id')->first() : null;
    $selectedAccountType = old('account_type', $versement?->account_type ?? 'national');
    $scope = (($module?->value ?? 'gares') === 'courrier') ? 'courrier' : 'gares';
    $isCashierScope = auth()->user()->canActAsCashierForScope($scope);
    $resolvedVirtualGare = $virtualGare ?? ((isset($versement) && $versement->gare && $versement->gare->is_virtual) ? $versement->gare : null);
    $garesActivityModes = collect($gares)->mapWithKeys(fn ($gare) => [(string) $gare->id => $gare->activity_mode ?? 'mixed'])->all();
    $chefActivityMode = $isCashierScope
        ? ($resolvedVirtualGare?->activity_mode ?? 'mixed')
        : (auth()->user()->primaryGare?->activity_mode ?? 'mixed');
@endphp

<div
    class="form-grid"
    data-account-form
    data-gare-activity-modes='@json($garesActivityModes)'
    data-chef-activity-mode="{{ $chefActivityMode }}"
>
    @if($isCashierScope)
        <div>
            <label>Gare de traitement</label>
            <input type="hidden" name="gare_id" value="{{ old('gare_id', $resolvedVirtualGare?->id) }}">
            <input type="text" value="{{ $resolvedVirtualGare?->name ?? 'Compte caissier' }}" disabled>
            <small>Affectee automatiquement a votre caisse.</small>
        </div>
    @elseif(!auth()->user()->canActAsChefForScope($scope))
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
    </div>

    <div>
        <label>Date de la recette</label>
        <input type="date" name="receipt_date" value="{{ old('receipt_date', $versement?->receipt_date?->toDateString() ?? '') }}" required>
    </div>

    <div>
        <label>Compte de versement</label>
        <select name="account_type" data-account-type required>
            <option value="inter" @selected($selectedAccountType === 'inter')>Inter (Ecobank)</option>
            <option value="national" data-account-national-option @selected($selectedAccountType === 'national')>National (Coris Bank)</option>
        </select>
        <small data-inter-only-account-hint hidden>Gare inter uniquement: seul le compte inter est autorise.</small>
    </div>

    <div>
        <label>Montant</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $versement?->amount ?? '') }}" required>
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
        <label>Bordereau justificatif obligatoire (max {{ $maxSizeKb }} Ko)</label>
        <input type="file" name="bordereau" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" capture="environment" required>
    </div>

    @if($latestPiece)
        <div class="col-span-2">
            <div class="doc-links">
                <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $latestPiece) }}" target="_blank">
                    <span class="icon">{!! app_icon('eye') !!}</span> Lire le bordereau actuel
                </a>
                <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $latestPiece) }}">
                    <span class="icon">{!! app_icon('download') !!}</span> Telecharger
                </a>
            </div>
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
                const interOnlyHint = wrapper.querySelector('[data-inter-only-account-hint]');
                const nationalOption = wrapper.querySelector('[data-account-national-option]');
                const gareActivityModes = JSON.parse(wrapper.getAttribute('data-gare-activity-modes') || '{}');
                const chefActivityMode = wrapper.getAttribute('data-chef-activity-mode') || 'mixed';

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

                function syncAccountRestrictions() {
                    if (!accountSelect) return;
                    const interOnly = isInterOnly();

                    if (nationalOption) {
                        nationalOption.disabled = interOnly;
                    }
                    if (interOnly) {
                        accountSelect.value = 'inter';
                    }
                    if (interOnlyHint) {
                        interOnlyHint.hidden = !interOnly;
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

                if (accountSelect) {
                    accountSelect.addEventListener('change', syncBankName);
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
                syncAccountRestrictions();
                syncBankName();
            });
        </script>
    @endpush
@endonce
