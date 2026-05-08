@php
    $scope = (($module?->value ?? 'gares') === 'courrier') ? 'courrier' : 'gares';
    $isCashierScope = auth()->user()->canActAsCashierForScope($scope);
    $resolvedVirtualGare = $virtualGare
        ?? ((isset($depense) && $depense->gare && $depense->gare->is_virtual) ? $depense->gare : null);
@endphp

<div class="form-grid">
    @if($isCashierScope)
        <div>
            <label>Gare de traitement</label>
            <input type="hidden" name="gare_id" value="{{ old('gare_id', $resolvedVirtualGare?->id) }}">
            <input type="text" value="{{ $resolvedVirtualGare?->name ?? 'Compte caissier' }}" disabled>
            <small>Affectee automatiquement a votre caisse.</small>
        </div>
    @elseif(auth()->user()->canActAsChefForScope($scope))
        <div>
            <label>Gare affectee</label>
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
        </div>
    @else
        <x-gare-picker
            :gares="$gares"
            datalistId="depense-gares"
            :selectedGareLabel="collect($gares)->firstWhere('id', (int) old('gare_id', $depense->gare_id ?? 0))?->name . ' - ' . collect($gares)->firstWhere('id', (int) old('gare_id', $depense->gare_id ?? 0))?->city"
            :selectedGareId="old('gare_id', $depense->gare_id ?? null)"
        />
    @endif

    <div>
        <label>Date operation</label>
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
        <label>Reference</label>
        <input type="text" name="reference" value="{{ old('reference', $depense->reference ?? '') }}">
    </div>
    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $depense->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Nom du justificatif</label>
        <input type="text" name="justificatif_name" value="{{ old('justificatif_name') }}" placeholder="Ex. Depense carburant 15-07-2025">
        <small>Optionnel. Le nom saisi sera utilise pour le fichier telecharge.</small>
    </div>
    <div>
        <label>Justificatif (max {{ $maxSizeKb }} Ko)</label>
        <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png" required>
    </div>
    @if(isset($depense))
        <div class="col-span-2">
            <label>Commentaire d'historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Motif de la modification">
        </div>
    @endif
</div>
