@php
    $isEdit = isset($recette);
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $recette->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' - '.$selectedGare->city) : null;
    $scope = (($module?->value ?? 'gares') === 'courrier') ? 'courrier' : 'gares';
    $garesActivityModes = collect($gares)->mapWithKeys(fn ($gare) => [(string) $gare->id => $gare->activity_mode ?? 'mixed'])->all();
    $chefActivityMode = auth()->user()->primaryGare?->activity_mode ?? 'mixed';
@endphp

<div
    class="form-grid recette-breakdown-form"
    data-recette-calculator
    data-scope="{{ $scope }}"
    data-gare-activity-modes='@json($garesActivityModes)'
    data-chef-activity-mode="{{ $chefActivityMode }}"
>
    @if(! auth()->user()->canActAsChefForScope($scope) || auth()->user()->canUseMultiGareEntry())
        <x-gare-picker
            :gares="$gares"
            datalistId="recette-gares"
            :selectedGareLabel="$selectedGareLabel"
            :selectedGareId="old('gare_id', $recette->gare_id ?? '')"
        />
    @else
        <div>
            <label>Gare affectee</label>
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
        </div>
    @endif

    <div>
        <label>Date operation</label>
        <input type="date" name="operation_date" value="{{ old('operation_date', optional($recette->operation_date ?? null)->format('Y-m-d') ?? now()->toDateString()) }}" required>
    </div>

    @if($scope === 'courrier')
        <div>
            <label>Recette courrier</label>
            <input type="number" step="1" min="0" name="ticket_national_amount" value="{{ old('ticket_national_amount', $isEdit ? $recette->amount : '') }}" data-clear-zero>
            <small>Le module courrier utilise une recette unique.</small>
        </div>
        <input type="hidden" name="ticket_inter_amount" value="0">
        <input type="hidden" name="bagage_inter_amount" value="0">
        <input type="hidden" name="bagage_national_amount" value="0">
        <input type="hidden" name="amount" value="{{ old('ticket_national_amount', $isEdit ? $recette->amount : '') }}">
    @else
        <div class="col-span-2">
            <div class="breakdown-grid">
                <div>
                    <label>Ventes tickets inter</label>
                    <input type="number" step="1" min="0" name="ticket_inter_amount" value="{{ old('ticket_inter_amount', $isEdit ? $recette->ticket_inter_amount : '') }}" data-recette-part="ticket_inter" data-clear-zero>
                </div>
                <div>
                    <label>Ventes tickets national</label>
                    <input type="number" step="1" min="0" name="ticket_national_amount" value="{{ old('ticket_national_amount', $isEdit ? $recette->ticket_national_amount : '') }}" data-recette-part="ticket_national" data-recette-national-input data-clear-zero>
                </div>
                <div>
                    <label>Transport bagages inter</label>
                    <input type="number" step="1" min="0" name="bagage_inter_amount" value="{{ old('bagage_inter_amount', $isEdit ? $recette->bagage_inter_amount : '') }}" data-recette-part="bagage_inter" data-clear-zero>
                </div>
                <div>
                    <label>Transport bagages national</label>
                    <input type="number" step="1" min="0" name="bagage_national_amount" value="{{ old('bagage_national_amount', $isEdit ? $recette->bagage_national_amount : '') }}" data-recette-part="bagage_national" data-recette-national-input data-clear-zero>
                </div>
            </div>
            <small data-inter-only-hint hidden>Cette gare est en mode inter uniquement. Les montants nationaux sont bloques a 0.</small>
            <small data-national-only-hint hidden>Cette gare est en mode national uniquement. Les montants inter sont bloques a 0.</small>
        </div>

        <div>
            <label>Recette inter (ticket inter + bagage inter)</label>
            <input type="number" step="1" min="0" value="{{ old('recette_inter_amount', $isEdit ? ($recette->ticket_inter_amount + $recette->bagage_inter_amount) : '') }}" data-recette-inter readonly>
        </div>

        <div>
            <label>Recette nationale (ticket national + bagage national)</label>
            <input type="number" step="1" min="0" value="{{ old('recette_national_amount', $isEdit ? ($recette->ticket_national_amount + $recette->bagage_national_amount) : '') }}" data-recette-national readonly>
        </div>

        <div>
            <label>Montant total calcule</label>
            <input type="number" step="1" min="0" name="amount" value="{{ old('amount', $isEdit ? $recette->amount : '') }}" data-recette-total readonly>
            <small>Le montant total est calcule automatiquement.</small>
        </div>
    @endif

    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $recette->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Nom du fichier justificatif</label>
        <input type="text" name="justificatif_name" value="{{ old('justificatif_name') }}" placeholder="Ex. Recette gare Abidjan 15-07-2025">
    </div>
    <div>
        <label>Fichier justificatif {{ isset($recette) ? '(optionnel en modification)' : '(obligatoire)' }}</label>
        <input type="file" name="justificatifs[]" accept="image/*,.heic,.heif,.webp,.jpg,.jpeg,.png,.pdf,application/pdf" multiple @required(!isset($recette))>
        <small>Vous pouvez joindre plusieurs fichiers (PDF jusqu'a 10 Mo par fichier).</small>
    </div>
    @isset($recette)
        <div class="col-span-2">
            <label>Commentaire historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Ex. Correction apres verification">
        </div>
    @endisset
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-recette-calculator]').forEach(function (wrapper) {
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

                wrapper.querySelectorAll('[data-clear-zero]').forEach(function (input) {
                    input.addEventListener('input', function () {
                        sanitizeIntegerField(input);
                    });
                    input.addEventListener('focus', function () {
                        if ((input.value || '').trim() === '0') {
                            input.value = '';
                        }
                    });
                    input.addEventListener('blur', function () {
                        sanitizeIntegerField(input);
                    });
                });

                const scope = wrapper.getAttribute('data-scope') || 'gares';
                if (scope === 'courrier') {
                    return;
                }
                const parts = Array.from(wrapper.querySelectorAll('[data-recette-part]'));
                const total = wrapper.querySelector('[data-recette-total]');
                const interOutput = wrapper.querySelector('[data-recette-inter]');
                const nationalOutput = wrapper.querySelector('[data-recette-national]');
                const nationalInputs = Array.from(wrapper.querySelectorAll('[data-recette-national-input]'));
                const interInputs = [
                    wrapper.querySelector('[data-recette-part="ticket_inter"]'),
                    wrapper.querySelector('[data-recette-part="bagage_inter"]'),
                ].filter(Boolean);
                const interOnlyHint = wrapper.querySelector('[data-inter-only-hint]');
                const nationalOnlyHint = wrapper.querySelector('[data-national-only-hint]');
                const gareActivityModes = JSON.parse(wrapper.getAttribute('data-gare-activity-modes') || '{}');
                const chefActivityMode = wrapper.getAttribute('data-chef-activity-mode') || 'mixed';

                function selectedGareId() {
                    const hiddenInput = wrapper.querySelector('input[data-gare-id]');
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

                function syncInterOnlyMode() {
                    const interOnly = isInterOnly();
                    const nationalOnly = isNationalOnly();
                    nationalInputs.forEach(function (input) {
                        if (interOnly && !nationalOnly) {
                            input.value = '0';
                        }
                        input.readOnly = interOnly && !nationalOnly;
                    });
                    interInputs.forEach(function (input) {
                        if (nationalOnly && !interOnly) {
                            input.value = '0';
                        }
                        input.readOnly = nationalOnly && !interOnly;
                    });
                    if (interOnlyHint) {
                        interOnlyHint.hidden = !interOnly || nationalOnly;
                    }
                    if (nationalOnlyHint) {
                        nationalOnlyHint.hidden = !nationalOnly || interOnly;
                    }
                }

                function valueFor(key) {
                    const input = wrapper.querySelector('[data-recette-part="' + key + '"]');
                    return parseInt(input ? (input.value || '0') : '0', 10) || 0;
                }

                function updateTotal() {
                    syncInterOnlyMode();
                    const hasTypedValue = parts.some(function (input) {
                        return (input.value || '').trim() !== '';
                    });
                    if (!hasTypedValue) {
                        total.value = '';
                        if (interOutput) interOutput.value = '';
                        if (nationalOutput) nationalOutput.value = '';
                        return;
                    }

                    const inter = valueFor('ticket_inter') + valueFor('bagage_inter');
                    const national = valueFor('ticket_national') + valueFor('bagage_national');
                    const sum = Math.round(inter + national);
                    total.value = String(sum);
                    if (interOutput) interOutput.value = String(Math.round(inter));
                    if (nationalOutput) nationalOutput.value = String(Math.round(national));
                }

                parts.forEach(function (input) {
                    input.addEventListener('input', updateTotal);
                    input.addEventListener('focus', function () {
                        if ((input.value || '').trim() === '0') {
                            input.value = '';
                            updateTotal();
                        }
                    });
                });
                const gareLabelInput = wrapper.querySelector('input[data-gare-label]');
                if (gareLabelInput) {
                    gareLabelInput.addEventListener('change', function () {
                        window.setTimeout(updateTotal, 0);
                    });
                    gareLabelInput.addEventListener('input', function () {
                        window.setTimeout(updateTotal, 0);
                    });
                }
                const gareHiddenInput = wrapper.querySelector('input[data-gare-id]');
                if (gareHiddenInput) {
                    gareHiddenInput.addEventListener('change', updateTotal);
                    gareHiddenInput.addEventListener('input', updateTotal);
                }

                updateTotal();
            });
        </script>
    @endpush
@endonce
