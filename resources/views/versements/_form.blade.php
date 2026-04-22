@php
    $maxSizeKb = $maxSizeKb ?? (int) env('JUSTIFICATIF_MAX_SIZE_KB', 5120);
    $selectedGare = collect($gares)->firstWhere('id', (int) old('gare_id', $versement?->gare_id ?? 0));
    $selectedGareLabel = $selectedGare ? ($selectedGare->name.' — '.$selectedGare->city) : ($defaultGareLabel ?? null);
    $latestPiece = isset($versement) ? $versement?->justificatives?->sortByDesc('id')->first() : null;
@endphp

<div class="form-grid">
    @unless(auth()->user()->isChefDeGare())
        <x-gare-picker
            :gares="$gares"
            datalistId="versement-gares"
            :selectedGareLabel="$selectedGareLabel"
            :selectedGareId="old('gare_id', $versement?->gare_id ?? null)"
        />
    @else
        <div>
            <label>Gare affectée</label>
            <input type="hidden" name="gare_id" value="{{ auth()->user()->gare_id }}">
            <input type="text" value="{{ auth()->user()->primaryGare?->name }}" disabled>
            <small>Issue de votre connexion.</small>
        </div>
    @endunless

    <div>
        <label>Date opération</label>
        <input type="date" name="operation_date" value="{{ old('operation_date', $versement?->operation_date?->toDateString() ?? now()->toDateString()) }}" required>
    </div>

    <div>
        <label>Date de la recette</label>
        <input type="date" name="receipt_date" value="{{ old('receipt_date', $versement?->receipt_date?->toDateString() ?? '') }}" required>
    </div>

    <div>
        <label>Montant</label>
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $versement?->amount ?? '') }}" required>
        <small>Saisissez manuellement le montant du versement.</small>
    </div>

    <div>
        <label>Banque</label>
        <input type="text" name="bank_name" value="{{ old('bank_name', $versement?->bank_name ?? '') }}">
    </div>

    <div>
        <label>Référence (nom de l'agence)</label>
        <input type="text" name="reference" value="{{ old('reference', $versement?->reference ?? '') }}">
    </div>

    <div class="col-span-2">
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $versement?->description ?? '') }}</textarea>
    </div>

    <div>
        <label>Nom du bordereau</label>
        <input type="text" name="bordereau_name" value="{{ old('bordereau_name') }}" placeholder="Ex. Bordereau Ecobank 15-08-2026">
        <small>Optionnel. Le nom saisi sera utilisé pour la lecture et le téléchargement.</small>
    </div>

    <div>
        <label>{{ isset($versement) ? 'Ajouter / remplacer un bordereau' : 'Bordereau justificatif obligatoire' }} (max {{ $maxSizeKb }} Ko)</label>
        <input type="file" name="bordereau" accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" capture="environment" @if(! isset($versement)) required @endif>
        <small>Import simple PDF ou photo mobile, sans préremplissage automatique.</small>
    </div>

    @if($latestPiece)
        <div class="col-span-2">
            <div class="doc-links">
                <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.preview', $latestPiece) }}" target="_blank">
                    <span class="icon">{!! app_icon('eye') !!}</span> Lire le bordereau actuel
                </a>
                <a class="btn btn-sm btn-outline" href="{{ route('justificatifs.download', $latestPiece) }}">
                    <span class="icon">{!! app_icon('download') !!}</span> Télécharger
                </a>
            </div>
        </div>
    @endif

    @if(isset($versement))
        <div class="col-span-2">
            <label>Commentaire d’historique</label>
            <input type="text" name="history_comment" value="{{ old('history_comment') }}" placeholder="Motif de la modification">
        </div>
    @endif
</div>
