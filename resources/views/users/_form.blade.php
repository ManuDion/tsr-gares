<div class="form-grid">
    <div>
        <label>Nom</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required>
    </div>
    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
    </div>
    <div>
        <label>Mot de passe {{ isset($user) ? '(laisser vide pour conserver)' : '' }}</label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }}>
    </div>
    <div>
        <label>Rôle</label>
        <select name="role" required data-user-role>
            @foreach($roles as $role)
                <option value="{{ $role['value'] }}" @selected(old('role', $user->role->value ?? '') === $role['value'])>{{ $role['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div data-chef-gare-section class="col-span-2">
        <label>Gare principale (chef de gare)</label>
        <select name="gare_id">
            <option value="">Sélectionner une gare</option>
            @foreach($gares as $gare)
                <option value="{{ $gare->id }}" @selected((string) old('gare_id', $user->gare_id ?? '') === (string) $gare->id)>{{ $gare->name }} — {{ $gare->city }}</option>
            @endforeach
        </select>
    </div>

    <div data-caissiere-section class="col-span-2">
        <div class="inline-checks">
            <label class="checkbox-line">
                <input type="checkbox" name="all_gares" value="1" data-all-gares @checked(old('all_gares', isset($user) && $user->gares->count() === $gares->count()))>
                <span>Toutes les gares actives</span>
            </label>
        </div>

        <label>Gares affectées (caissière)</label>
        <select name="zone_gares[]" multiple size="7" data-zone-gares>
            @php($selectedZoneGares = old('zone_gares', isset($user) ? $user->gares->pluck('id')->map(fn($id)=>(string)$id)->all() : []))
            @foreach($gares as $gare)
                <option value="{{ $gare->id }}" @selected(in_array((string) $gare->id, $selectedZoneGares, true))>{{ $gare->name }} — {{ $gare->city }}</option>
            @endforeach
        </select>
        <small>Quand le rôle est Caissière, vous pouvez cocher plusieurs gares ou toutes les gares.</small>
    </div>

    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
            <span>Compte actif</span>
        </label>
    </div>
</div>
