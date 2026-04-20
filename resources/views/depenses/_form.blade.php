<div class="form-grid">
    @unless(auth()->user()->isChefDeGare())
        <x-gare-picker :gares="$gares" datalistId="depense-gares" :selectedGareLabel="collect($gares)->firstWhere('id', (int) old('gare_id', $depense->gare_id ?? 0))?->name . ' — ' . collect($gares)->firstWhere('id', (int) old('gare_id', $depense->gare_id ?? 0))?->city" :selectedGareId="old('gare_id', $depense->gare_id ?? null)" />
    @else
        <div>
            <label>Gare affectée</label>
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
        </div>
    @endunless

    <div>
        <label>Date opération</label>
        <input type="date" name="operation_date" value="{{ old('operation_date', isset($depense) && $depense->operation_date ? $depense->operation_date->toDateString() : now()->toDateString()) }}" required>
    </div>
    <div>
        <label>Montant</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $depense->amount ?? '') }}" required>
    </div>
    <div>
        <label>Motif</label>
        <input type="text" name="motif" value="{{ old('motif', $depense->motif ?? '') }}" required>
    </div>
    <div>
        <label>Référence</label>
        <input type="text" name="reference" value="{{ old('reference', $depense->reference ?? '') }}">
    </div>
    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $depense->description ?? '') }}</textarea>
    </div>
    <div class="col-span-2">
        <label>Justificatif (max {{ $maxSizeKb }} Ko)</label>
        <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png">
    </div>
    @if(isset($depense))
        <div class="col-span-2">
            <label>Commentaire d’historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Motif de la modification">
        </div>
    @endif
</div>
