@php($document = $document ?? null)
@php($documentTypes = ['Permis de conduire', 'Vignettes', 'Visites techniques'])
<div class="form-grid">
    <div>
        <label>Type de document</label>
        <input type="text" name="document_type" list="document-types" value="{{ old('document_type', $document->document_type ?? '') }}" required placeholder="Permis de conduire, Vignettes, Visites techniques...">
        <datalist id="document-types">
            @foreach($documentTypes as $type)
                <option value="{{ $type }}"></option>
            @endforeach
        </datalist>
        <small>Vous pouvez choisir un type par défaut ou saisir un autre document réglementaire.</small>
    </div>
    <div>
        <label>Intitulé / Référence</label>
        <input type="text" name="label" value="{{ old('label', $document->label ?? '') }}" placeholder="Ex. Camion TSR 01">
    </div>
    <div>
        <label>Date d’expiration</label>
        <input type="date" name="expires_at" value="{{ old('expires_at', isset($document) && $document->expires_at ? $document->expires_at->toDateString() : '') }}" required>
    </div>
    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $document->is_active ?? true))>
            <span>Document actif</span>
        </label>
    </div>
    <div class="col-span-2">
        <label>Document PDF</label>
        <input type="file" name="document" accept="application/pdf" {{ isset($document) ? '' : 'required' }}>
        <small>Le document est conservé au format PDF avec son nom d’origine.</small>
    </div>
    <div class="col-span-2">
        <label>Notes</label>
        <textarea name="notes" rows="4">{{ old('notes', $document->notes ?? '') }}</textarea>
    </div>
</div>
