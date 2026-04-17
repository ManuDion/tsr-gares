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
        <small>La gare principale n'apparaît que pour le rôle Chef de gare.</small>
    </div>

    <div data-caissiere-section class="col-span-2">
        <div class="assignment-card">
            <div class="assignment-head">
                <div>
                    <strong>Affectation des gares</strong>
                    <small>La caissière peut être rattachée à plusieurs gares ou à toutes les gares actives.</small>
                </div>
                <label class="checkbox-line">
                    <input type="checkbox" name="all_gares" value="1" data-all-gares @checked(old('all_gares', isset($user) && $user->gares->count() === $gares->count()))>
                    <span>Toutes les gares actives</span>
                </label>
            </div>

            @php($selectedZoneGares = old('zone_gares', isset($user) ? $user->gares->pluck('id')->map(fn($id)=>(string)$id)->all() : []))

            <label>Gares affectées</label>
            <div class="checkbox-grid" data-zone-gares-group>
                @foreach($gares as $gare)
                    <label class="checkbox-card">
                        <input type="checkbox" name="zone_gares[]" value="{{ $gare->id }}" data-zone-gares @checked(in_array((string) $gare->id, $selectedZoneGares, true))>
                        <span>
                            <strong>{{ $gare->name }}</strong>
                            <small>{{ $gare->city }}</small>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="helper-list">
                <small>Cochez une ou plusieurs gares pour affecter la caissière.</small>
                <small>Si « Toutes les gares actives » est coché, la liste des gares est désactivée automatiquement.</small>
            </div>
        </div>
    </div>

    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
            <span>Compte actif</span>
        </label>
    </div>
</div>
