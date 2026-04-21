@php
    $selectedModule = old('module', isset($user) ? ($user->assignedModule()?->value ?? 'gares') : 'gares');
    $selectedRole = old('role', isset($user) ? $user->role?->value : null);
    $selectedZoneGares = old('zone_gares', isset($user) ? $user->gares->pluck('id')->map(fn($id)=>(string)$id)->all() : []);
@endphp

<div class="form-grid">
    <div class="col-span-2">
        <label>Nom complet</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required>
    </div>

    <div>
        <label>Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" required placeholder="+225 0700000000">
    </div>

    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
    </div>

    <div>
        <label>Mot de passe {{ isset($user) ? '(laisser vide pour conserver)' : '' }}</label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }}>
        <small>Le mot de passe sera personnalisé à la première connexion.</small>
    </div>

    @if(isset($user))
        <div>
            <label class="checkbox-line">
                <input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $user->must_change_password ?? false))>
                <span>Redemander la personnalisation du mot de passe</span>
            </label>
        </div>
    @endif

    <div class="col-span-2">
        <label>Service / module principal</label>
        <div class="radio-grid radio-grid-services">
            @foreach($moduleOptions as $module)
                <label class="radio-card">
                    <input type="radio" name="module" value="{{ $module['value'] }}" data-user-module @checked($selectedModule === $module['value'])>
                    <span>
                        <strong>{{ $module['label'] }}</strong>
                        <small>{{ $module['description'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <label>Rôle</label>
        <select
            name="role"
            required
            data-user-role
            data-current-role="{{ $selectedRole }}"
            data-role-options='@json($roleOptionsByModule)'>
        </select>
        <small>La liste des rôles s’adapte automatiquement au service choisi.</small>
    </div>

    <div>
        <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
            <span>Compte actif</span>
        </label>
    </div>

    <div data-chef-gare-section class="col-span-2">
        <label>Gare principale</label>
        <select name="gare_id">
            <option value="">Sélectionner une gare</option>
            @foreach($gares as $gare)
                <option value="{{ $gare->id }}" @selected((string) old('gare_id', $user->gare_id ?? '') === (string) $gare->id)>{{ $gare->name }} — {{ $gare->city }}</option>
            @endforeach
        </select>
        <small>Utilisé pour les rôles Chef de gare et Agent courrier gare.</small>
    </div>

    <div data-multi-gares-section class="col-span-2">
        <div class="assignment-card">
            <div class="assignment-head">
                <div>
                    <strong>Affectation des gares</strong>
                    <small>Pour les rôles Caissier gare et Caissier courrier.</small>
                </div>
                <label class="checkbox-line">
                    <input type="checkbox" name="all_gares" value="1" data-all-gares @checked(old('all_gares', isset($user) && $user->gares->count() === $gares->count()))>
                    <span>Toutes les gares actives</span>
                </label>
            </div>

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
        </div>
    </div>

    <div class="col-span-2">
        <div class="panel panel-muted">
            <strong>Principe de fonctionnement</strong>
            <p class="text-muted">
                L’utilisateur est affecté à un service. Le service détermine les rôles proposés et donc l’accès au bon module, au bon dashboard et aux bons menus.
                Les rôles Administrateur et Responsable conservent une visibilité générale sur l’ensemble du progiciel.
            </p>
        </div>
    </div>
</div>
