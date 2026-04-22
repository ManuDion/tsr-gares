@php
    $entry = $entry ?? [];
    $oldPrefix = "entries.$index";
    $selectedGare = collect($gares)->firstWhere('id', (int) data_get($entry, 'gare_id'));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' — '.$selectedGare->city) : null;
@endphp

<div class="panel depense-entry-card" data-depense-entry>
    <div class="entry-head">
        <h3 data-entry-title>Dépense {{ is_numeric($index) ? ((int) $index + 1) : '' }}</h3>
        <button class="btn btn-sm btn-outline" type="button" data-remove-depense>Retirer</button>
    </div>

    <div class="form-grid">
        @unless(auth()->user()->isChefDeGare())
            <x-gare-picker
                :gares="$gares"
                :datalistId="'depense-gares-'.$index"
                :selectedGareLabel="old($oldPrefix.'.gare_id') ? (collect($gares)->firstWhere('id', (int) old($oldPrefix.'.gare_id'))?->name.' — '.collect($gares)->firstWhere('id', (int) old($oldPrefix.'.gare_id'))?->city) : $selectedGareLabel"
                :selectedGareId="old($oldPrefix.'.gare_id', data_get($entry, 'gare_id'))"
                :hiddenName="'entries['.$index.'][gare_id]'"
            />
        @else
            <div>
                <label>Gare affectée</label>
                <input type="hidden" name="entries[{{ $index }}][gare_id]" value="{{ auth()->user()->gare_id }}">
                <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
            </div>
        @endunless

        <div>
            <label>Date opération</label>
            <input type="date" name="entries[{{ $index }}][operation_date]" value="{{ old($oldPrefix.'.operation_date', data_get($entry, 'operation_date', now()->toDateString())) }}" required>
        </div>
        <div>
            <label>Montant</label>
            <input type="number" step="0.01" min="0" name="entries[{{ $index }}][amount]" value="{{ old($oldPrefix.'.amount', data_get($entry, 'amount')) }}" required>
        </div>
        <div>
            <label>Motif</label>
            <input type="text" name="entries[{{ $index }}][motif]" value="{{ old($oldPrefix.'.motif', data_get($entry, 'motif')) }}" required>
        </div>
        <div>
            <label>Référence</label>
            <input type="text" name="entries[{{ $index }}][reference]" value="{{ old($oldPrefix.'.reference', data_get($entry, 'reference')) }}">
        </div>
        <div class="col-span-2">
            <label>Description</label>
            <textarea name="entries[{{ $index }}][description]" rows="3">{{ old($oldPrefix.'.description', data_get($entry, 'description')) }}</textarea>
        </div>
        <div>
            <label>Nom du justificatif</label>
            <input type="text" name="entries[{{ $index }}][justificatif_name]" value="{{ old($oldPrefix.'.justificatif_name', data_get($entry, 'justificatif_name')) }}" placeholder="Ex. Dépense billetage {{ is_numeric($index) ? ((int) $index + 1) : '' }}">
            <small>Optionnel. Le nom saisi sera utilisé pour le fichier téléchargé.</small>
        </div>
        <div>
            <label>Justificatif (max {{ $maxSizeKb }} Ko)</label>
            <input type="file" name="entries[{{ $index }}][justificatif]" accept=".pdf,.jpg,.jpeg,.png">
        </div>
    </div>
</div>
