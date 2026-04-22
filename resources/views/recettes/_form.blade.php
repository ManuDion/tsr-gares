@php
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $recette->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' — '.$selectedGare->city) : null;
@endphp

<div class="form-grid recette-breakdown-form" data-recette-calculator>
    @unless(auth()->user()->isChefDeGare())
        <x-gare-picker
            :gares="$gares"
            datalistId="recette-gares"
            :selectedGareLabel="$selectedGareLabel"
            :selectedGareId="old('gare_id', $recette->gare_id ?? '')"
        />
    @else
        <div>
            <label>Gare affectée</label>
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
        </div>
    @endunless

    <div>
        <label>Date opération</label>
        <input type="date" name="operation_date" value="{{ old('operation_date', optional($recette->operation_date ?? null)->format('Y-m-d') ?? now()->toDateString()) }}" required>
    </div>

    <div class="col-span-2">
        <div class="breakdown-grid">
            <div>
                <label>Ventes tickets inter</label>
                <input type="number" step="0.01" min="0" name="ticket_inter_amount" value="{{ old('ticket_inter_amount', $recette->ticket_inter_amount ?? 0) }}" data-recette-part required>
            </div>
            <div>
                <label>Ventes tickets national</label>
                <input type="number" step="0.01" min="0" name="ticket_national_amount" value="{{ old('ticket_national_amount', $recette->ticket_national_amount ?? 0) }}" data-recette-part required>
            </div>
            <div>
                <label>Transport bagages inter</label>
                <input type="number" step="0.01" min="0" name="bagage_inter_amount" value="{{ old('bagage_inter_amount', $recette->bagage_inter_amount ?? 0) }}" data-recette-part required>
            </div>
            <div>
                <label>Transport bagages national</label>
                <input type="number" step="0.01" min="0" name="bagage_national_amount" value="{{ old('bagage_national_amount', $recette->bagage_national_amount ?? 0) }}" data-recette-part required>
            </div>
        </div>
    </div>

    <div>
        <label>Montant total calculé</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $recette->amount ?? 0) }}" data-recette-total readonly required>
        <small>Ce montant est calculé automatiquement à partir des 4 types de recette.</small>
    </div>

    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $recette->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Nom du fichier justificatif</label>
        <input type="text" name="justificatif_name" value="{{ old('justificatif_name') }}" placeholder="Ex. Recette gare Abidjan 15-07-2025">
        <small>Optionnel. Si renseigné, ce nom sera utilisé lors du téléchargement.</small>
    </div>
    <div>
        <label>Fichier justificatif</label>
        <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf">
        <small>Vous pouvez joindre un justificatif de recette pour lecture ou téléchargement ultérieur.</small>
    </div>
    @isset($recette)
        <div class="col-span-2">
            <label>Commentaire historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Ex. Correction du montant après vérification">
        </div>
    @endisset
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-recette-calculator]').forEach(function (wrapper) {
                const parts = Array.from(wrapper.querySelectorAll('[data-recette-part]'));
                const total = wrapper.querySelector('[data-recette-total]');

                function updateTotal() {
                    const sum = parts.reduce((carry, input) => carry + (parseFloat(input.value || '0') || 0), 0);
                    total.value = sum.toFixed(2);
                }

                parts.forEach(function (input) {
                    input.addEventListener('input', updateTotal);
                });

                updateTotal();
            });
        </script>
    @endpush
@endonce
