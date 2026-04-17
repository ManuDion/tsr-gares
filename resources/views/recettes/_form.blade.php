<div class="form-grid">
    @unless(auth()->user()->isChefDeGare())
        <x-gare-picker :gares="$gares" datalistId="recette-gares" :selectedGareLabel="collect($gares)->firstWhere('id', (int) old('gare_id', $recette->gare_id ?? 0))?->name . ' — ' . collect($gares)->firstWhere('id', (int) old('gare_id', $recette->gare_id ?? 0))?->city" :selectedGareId="old('gare_id', $recette->gare_id ?? '')" />
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
    <div>
        <label>Montant</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $recette->amount ?? '') }}" required>
    </div>
    <div>
        <label>Référence</label>
        <input type="text" name="reference" value="{{ old('reference', $recette->reference ?? '') }}">
    </div>
    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $recette->description ?? '') }}</textarea>
    </div>
    <div class="col-span-2">
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
