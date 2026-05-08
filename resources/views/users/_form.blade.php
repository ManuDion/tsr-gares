@php
    $selectedModule = (string) old('module', isset($user) ? ($user->assignedModule()?->value ?? '') : '');
    $selectedRole = old('role', isset($user) ? $user->role?->value : null);
    $selectedZoneGares = old('zone_gares', isset($user) ? $user->gares->pluck('id')->map(fn ($id) => (string) $id)->all() : []);
    $selectedModules = old('modules', isset($user) ? $user->moduleMemberships() : []);
    if ($selectedModule !== '' && ! in_array($selectedModule, $selectedModules, true)) {
        $selectedModules[] = $selectedModule;
    }
@endphp

<div class="form-grid">
    <div class="col-span-2">
        <label>Nom complet</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required>
    </div>

    <div>
        <label>Telephone</label>
        <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" required placeholder="+225 0700000000">
    </div>

    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
    </div>

    <div>
        <label>Mot de passe {{ isset($user) ? '(laisser vide pour conserver)' : '' }}</label>
        <input type="password" name="password" {{ isset($user) ? '' : 'required' }}>
        <small>Le mot de passe sera personalise a la premiere connexion.</small>
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
            <label class="radio-card">
                <input type="radio" name="module" value="" data-user-module @checked($selectedModule === '')>
                <span>
                    <strong>Aucun service</strong>
                    <small>Supervision globale. Reserve aux roles Administrateur et Responsable.</small>
                </span>
            </label>

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
        <small>Le module principal pilote le role principal et l'espace ouvert par defaut.</small>
    </div>

    <div class="col-span-2">
        <label>Services rattaches (max 2)</label>
        <div class="checkbox-grid">
            @foreach($moduleOptions as $module)
                <label class="checkbox-card">
                    <input type="checkbox" name="modules[]" value="{{ $module['value'] }}" @checked(in_array($module['value'], $selectedModules, true))>
                    <span>
                        <strong>{{ $module['short_label'] }}</strong>
                        <small>{{ $module['label'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        <small>Exemple: un meme utilisateur peut etre rattache aux services Gares et Courrier.</small>
    </div>

    <div>
        <label>Role</label>
        <select
            name="role"
            required
            data-user-role
            data-current-role="{{ $selectedRole }}"
            data-role-options='@json($roleOptionsByModule)'>
        </select>
        <small>La liste des roles s'adapte automatiquement au service choisi. Sans service, seuls Administrateur et Responsable sont proposes.</small>
        <div class="badge badge-info" data-supervision-label hidden style="margin-top:.5rem;">
            <span data-supervision-label-text></span>
        </div>
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
            <option value="">Selectionner une gare</option>
            @foreach($gares as $gare)
                <option value="{{ $gare->id }}" @selected((string) old('gare_id', $user->gare_id ?? '') === (string) $gare->id)>{{ $gare->name }} - {{ $gare->city }}</option>
            @endforeach
        </select>
        <small>Utilise pour les roles Chef de gare et Agent courrier gare.</small>
    </div>

    <div data-multi-gares-section class="col-span-2">
        <div class="assignment-card">
            <div class="assignment-head">
                <div>
                    <strong>Affectation des gares</strong>
                    <small>Pour les roles Caissier gare, Caissier courrier et Verificateur.</small>
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
                L'utilisateur est affecte a un service. Le service determine les roles proposes et donc l'acces au bon module.
                Les roles Administrateur et Responsable conservent une visibilite generale sur l'ensemble du progiciel.
            </p>
        </div>
    </div>
</div>
