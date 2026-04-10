<div data-gare-autocomplete>
    <label>Gare</label>
    <input type="text" list="{{ $datalistId }}" data-gare-label value="{{ $selectedGareLabel ?? '' }}" placeholder="Tapez une ville ou une gare" required>
    <input type="hidden" name="gare_id" data-gare-id value="{{ old('gare_id', $selectedGareId ?? '') }}">
    <datalist id="{{ $datalistId }}">
        @foreach($gares as $gare)
            <option value="{{ $gare->name }} — {{ $gare->city }}" data-id="{{ $gare->id }}"></option>
        @endforeach
    </datalist>
    <small>Champ filtré : ex. Yamoussoukro, Yopougon, Bouaké...</small>
</div>
