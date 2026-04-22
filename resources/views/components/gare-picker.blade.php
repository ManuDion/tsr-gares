@props([
    'gares' => [],
    'datalistId',
    'selectedGareLabel' => null,
    'selectedGareId' => null,
    'hiddenName' => 'gare_id',
    'label' => 'Gare',
    'placeholder' => 'Tapez une ville ou une gare',
    'required' => true,
])

<div data-gare-autocomplete>
    <label>{{ $label }}</label>
    <input type="text" list="{{ $datalistId }}" data-gare-label value="{{ $selectedGareLabel ?? '' }}" placeholder="{{ $placeholder }}" @if($required) required @endif>
    <input type="hidden" name="{{ $hiddenName }}" data-gare-id value="{{ $selectedGareId ?? '' }}">
    <datalist id="{{ $datalistId }}">
        @foreach($gares as $gare)
            <option value="{{ $gare->name }} — {{ $gare->city }}" data-id="{{ $gare->id }}"></option>
        @endforeach
    </datalist>
    <small>Champ filtré : ex. Yamoussoukro, Yopougon, Bouaké...</small>
</div>
