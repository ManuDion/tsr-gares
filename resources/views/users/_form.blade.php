@php
    $forcedModule = $forcedModule ?? null;
    $allowNoModuleOption = $allowNoModuleOption ?? true;
    $selectedModule = (string) old('module', isset($user) ? ($user->assignedModule()?->value ?? '') : ($forcedModule ?? ''));
    if ($forcedModule && $selectedModule !== $forcedModule) {
        $selectedModule = $forcedModule;
    }
    $selectedRole = old('role', isset($user) ? $user->role?->value : null);
    $selectedZoneGares = old('zone_gares', isset($user) ? $user->gares->pluck('id')->map(fn ($id) => (string) $id)->all() : []);
    $selectedModules = old('modules', isset($user) ? $user->moduleMemberships() : []);
    $allowMultiGareEntry = old('allow_multi_gare_entry', isset($user) ? (bool) $user->allow_multi_gare_entry : false);
    $cashierCollectionMode = old('cashier_collection_mode', isset($user) ? $user->cashierCollectionMode() : \App\Models\User::CASHIER_COLLECTION_BOTH);
    $hrServiceOptions = collect($hrServiceOptions ?? [])->filter()->values();
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
        <label>Type de contrat</label>
        <input type="text" name="contract_type" value="{{ old('contract_type', $user->contract_type ?? '') }}" placeholder="Ex. CDI, CDD, Stage">
    </div>

    <div>
        <label>Lieu d'affectation</label>
        <input type="text" name="assignment_location" list="user-assignment-gares" value="{{ old('assignment_location', $user->assignment_location ?? '') }}" placeholder="Tapez pour filtrer les gares (ex: adja)">
        <datalist id="user-assignment-gares">
            @foreach($gares as $gare)
                <option value="{{ $gare->name }}">{{ $gare->name }} - {{ $gare->city }}</option>
            @endforeach
        </datalist>
        <small>Le lieu d'affectation doit correspondre à une gare.</small>
    </div>

    <div>
        <label>Service (RH)</label>
        <input type="text" name="hr_service" list="user-hr-services" value="{{ old('hr_service', $user->hr_service ?? '') }}" placeholder="Tapez pour filtrer ou ajouter un service">
        <datalist id="user-hr-services">
            @foreach($hrServiceOptions as $serviceName)
                <option value="{{ $serviceName }}">{{ $serviceName }}</option>
            @endforeach
        </datalist>
        <small>La liste est évolutive: vous pouvez saisir un nouveau service si besoin.</small>
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
            @if($allowNoModuleOption)
                <label class="radio-card">
                    <input type="radio" name="module" value="" data-user-module @checked($selectedModule === '')>
                    <span>
                        <strong>Aucun service</strong>
                        <small>Supervision globale. Reserve aux roles Administrateur et Responsable.</small>
                    </span>
                </label>
            @endif

            @foreach($moduleOptions as $module)
                <label class="radio-card">
                    <input
                        type="radio"
                        name="module"
                        value="{{ $module['value'] }}"
                        data-user-module
                        @checked($selectedModule === $module['value'])
                        @disabled($forcedModule && $forcedModule !== $module['value'])>
                    <span>
                        <strong>{{ $module['label'] }}</strong>
                        <small>{{ $module['description'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        <small>Le module principal pilote le role principal et l'espace ouvert par defaut.</small>
        @if($forcedModule)
            <input type="hidden" name="module" value="{{ $forcedModule }}">
        @endif
    </div>

    <div class="col-span-2">
        <label>Services rattaches (max 2)</label>
        <div class="checkbox-grid">
            @foreach($moduleOptions as $module)
                <label class="checkbox-card">
                    <input
                        type="checkbox"
                        name="modules[]"
                        value="{{ $module['value'] }}"
                        @checked(in_array($module['value'], $selectedModules, true) || ($forcedModule && $forcedModule === $module['value']))
                        @disabled($forcedModule && $forcedModule !== $module['value'])>
                    <span>
                        <strong>{{ $module['short_label'] }}</strong>
                        <small>{{ $module['label'] }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        <small>Exemple: un meme utilisateur peut etre rattache aux services Gares et Courrier.</small>
        @if($forcedModule)
            <input type="hidden" name="modules[]" value="{{ $forcedModule }}">
        @endif
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
        <div class="badge badge-info supervision-badge" data-supervision-label hidden>
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

    <div data-cashier-collection-section class="col-span-2" hidden>
        <label>Type de recettes a recuperer (caissier)</label>
        <select name="cashier_collection_mode" data-cashier-collection-mode>
            <option value="both" @selected($cashierCollectionMode === 'both')>Recettes nationales et internationales</option>
            <option value="national_only" @selected($cashierCollectionMode === 'national_only')>Recettes nationales uniquement</option>
            <option value="inter_only" @selected($cashierCollectionMode === 'inter_only')>Recettes internationales uniquement</option>
        </select>
        <small>Ce parametre limite les montants proposes au caissier lors de la validation des receptions.</small>
    </div>

    <div class="col-span-2">
        <label class="checkbox-line">
            <input type="checkbox" name="allow_multi_gare_entry" value="1" data-allow-multi-gare-entry @checked($allowMultiGareEntry)>
            <span>Autoriser la saisie sur plusieurs gares pour cet utilisateur</span>
        </label>
        <small>Activez cette option pour les profils specifiques qui doivent enregistrer des operations sur plusieurs gares.</small>
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
