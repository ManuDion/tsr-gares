<div class="form-grid">
    <div>
        <label>Code</label>
        <input type="text" name="code" value="{{ old('code', $gare->code ?? '') }}" required>
    </div>
    <div>
        <label>Nom</label>
        <input type="text" name="name" value="{{ old('name', $gare->name ?? '') }}" required>
    </div>
    <div>
        <label>Ville</label>
        <input type="text" name="city" value="{{ old('city', $gare->city ?? '') }}" required>
    </div>
    <div>
        <label>Zone</label>
        <input type="text" name="zone" value="{{ old('zone', $gare->zone ?? '') }}">
    </div>
    <div class="col-span-2">
        <label>Adresse</label>
        <input type="text" name="address" value="{{ old('address', $gare->address ?? '') }}">
    </div>
    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gare->is_active ?? true))>
            <span>Gare active</span>
        </label>
    </div>
</div>
