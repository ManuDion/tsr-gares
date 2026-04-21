@props([
    'gares' => [],
    'datalistId' => 'gare-picker',
    'selectedGareLabel' => null,
    'selectedGareId' => null,
    'hiddenName' => 'gare_id',
    'label' => 'Gare',
    'placeholder' => 'Filtrer une gare',
    'required' => true,
])

<div class="gare-filter-select" data-gare-filter-select>
    <label>{{ $label }}</label>
    <input type="text" value="{{ $selectedGareLabel ?? '' }}" placeholder="{{ $placeholder }}" data-gare-filter-input>
    <select name="{{ $hiddenName }}" data-gare-filter-target @if($required) required @endif>
        <option value="">Sélectionner une gare</option>
        @foreach($gares as $gare)
            <option value="{{ $gare->id }}"
                data-search="{{ strtolower($gare->name.' '.$gare->city) }}"
                @selected((string) $selectedGareId === (string) $gare->id)>
                {{ $gare->name }} — {{ $gare->city }}
            </option>
        @endforeach
    </select>
    <small>Saisissez quelques lettres pour filtrer la liste, puis choisissez la gare.</small>
</div>
