@php
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $depense->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' — '.$selectedGare->city) : null;
@endphp
<div class="form-grid">
    @if(auth()->user()->isChefDeGare() || auth()->user()->isAgentCourrierGare())
        <div>
            <label>Gare affectée</label>
            <input type="hidden" name="gare_id" value="{{ auth()->user()->gare_id }}">
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
        </div>
    @else
        <x-gare-picker :gares="$gares" datalistId="depense-gares" :selectedGareLabel="$selectedGareLabel" :selectedGareId="old('gare_id', $depense->gare_id ?? null)" />
    @endif

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
    <div>
        <label>Nom du justificatif</label>
        <input type="text" name="justificatif_name" value="{{ old('justificatif_name') }}" placeholder="Ex. Dépense carburant 15-07-2025">
        <small>Optionnel. À défaut, le nom utilisé sera : Module_Gare_Date opération.</small>
    </div>
    <div>
        <label>Justificatif (max {{ $maxSizeKb }} Ko)</label>
        <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" capture="environment">
        <small>PDF, image ou photo mobile. Selon le téléphone, vous pouvez recadrer avant validation.</small>
    </div>
    @if(isset($depense))
        <div class="col-span-2">
            <label>Commentaire d’historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Motif de la modification">
        </div>
    @endif
</div>
