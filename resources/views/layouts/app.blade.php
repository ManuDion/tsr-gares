<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="theme-color" content="#8a2433">
    <meta name="description" content="Progiciel intégré TSR.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @livewireStyles
</head>
<body class="app-body">
    @php
        use App\Enums\ServiceModule;
        use App\Support\ModuleContext;

        $user = auth()->user();
        $currentModule = ModuleContext::fromRequest(request(), $user);
        $accessibleModules = collect($user->accessibleModules());
        $notificationCount = $user
            ? \App\Models\NotificationHistory::query()
                ->where('user_id', $user->id)
                ->when($currentModule === ServiceModule::Documents, fn ($q) => $q->whereIn('type', ['document_expired', 'document_expiry_daily', 'document_expiry_weekly']))
                ->whereNull('read_at')
                ->count()
            : 0;

        $chatUnreadCount = $user
            ? \Illuminate\Support\Facades\DB::table('conversation_user as cu')
                ->join('chat_messages as m', 'm.conversation_id', '=', 'cu.conversation_id')
                ->where('cu.user_id', $user->id)
                ->where('m.user_id', '!=', $user->id)
                ->where(function ($query) {
                    $query->whereNull('cu.last_read_at')
                        ->orWhereColumn('m.created_at', '>', 'cu.last_read_at');
                })
                ->count()
            : 0;
    @endphp

    <div class="mobile-topbar">
        <button class="menu-toggle" type="button" data-menu-toggle>
            <span class="icon">{!! app_icon('menu') !!}</span>
            Menu
        </button>
        <strong>TSR · {{ $currentModule->shortLabel() }}</strong>
    </div>

    <div class="app-overlay" data-menu-close></div>

    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire">
                <div>
                    <strong>Progiciel TSR</strong>
                    <small>{{ $currentModule->label() }}</small>
                </div>
            </div>

            <div class="module-switcher">
                @foreach($accessibleModules as $module)
                    <a href="{{ route('dashboard', ['module' => $module->value]) }}" class="module-chip {{ $currentModule === $module ? 'active' : '' }}">
                        {{ $module->shortLabel() }}
                    </a>
                @endforeach
            </div>

            <nav class="nav-links">
                <a href="{{ route('dashboard', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('dashboard') !!}</span><span>Dashboard</span>
                </a>

                @if($currentModule->supportsFinancialFlows())
                    <a href="{{ route('recettes.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('recettes.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('wallet') !!}</span>
                        <span>{{ $currentModule === ServiceModule::Courrier ? 'Recettes courrier' : 'Recettes' }}</span>
                    </a>

                    <a href="{{ route('depenses.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('depenses.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('receipt') !!}</span>
                        <span>{{ $currentModule === ServiceModule::Courrier ? 'Dépenses courrier' : 'Dépenses' }}</span>
                    </a>

                    <a href="{{ route('versements.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('versements.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('bank') !!}</span>
                        <span>{{ $currentModule === ServiceModule::Courrier ? 'Versements courrier' : 'Versements' }}</span>
                    </a>

                    @if($currentModule === ServiceModule::Gares && $user->canAccessGaresModule() && ! $user->isChefDeGare())
                        <a href="{{ route('gares.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('gares.*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('train') !!}</span><span>Gares</span>
                        </a>
                    @endif

                    @if($user->hasGlobalVisibility())
                        <a href="{{ route('verifications.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('verifications.*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('checklist') !!}</span><span>Vérification</span>
                        </a>
                    @endif
                @endif

                @if($currentModule === ServiceModule::Documents && $user->canAccessAdministrativeDocumentsModule())
                    <a href="{{ route('administrative-documents.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('administrative-documents.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('shield') !!}</span><span>Documents administratifs</span>
                    </a>
                @endif

                @if($currentModule === ServiceModule::Rh && $user->canAccessRhModule())
                    <a href="{{ route('rh.employees.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('rh.employees.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('users') !!}</span><span>Dossiers du personnel</span>
                    </a>
                @endif

                <a href="{{ route('chat.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('chat.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('chat') !!}</span><span>Chat</span>
                    @if($chatUnreadCount > 0)
                        <span class="nav-badge">{{ $chatUnreadCount }}</span>
                    @endif
                </a>

                <a href="{{ route('notifications.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('bell') !!}</span>
                    <span>Notifications</span>
                    @if($notificationCount > 0)
                        <span class="nav-badge">{{ $notificationCount }}</span>
                    @endif
                </a>

                @if($user->hasGlobalVisibility())
                    <a href="{{ route('users.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('users') !!}</span><span>Utilisateurs</span>
                    </a>
                    <a href="{{ route('activity-logs.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('history') !!}</span><span>Historique système</span>
                    </a>
                @endif

                @if($user->hasGlobalVisibility() && $currentModule->supportsFinancialFlows())
                    <a href="{{ route('reports.performance', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('reports.performance') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('trophy') !!}</span><span>Top 5 & rapports</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->roleLabel() }} · {{ $user->moduleLabel() }}</small>
                    @if($user->isChefDeGare() || $user->isAgentCourrierGare())
                        <span class="user-chip">{{ $user->primaryGare?->name }}</span>
                    @elseif($user->isCaissierGare() || $user->isCaissierCourrier())
                        <span class="user-chip">{{ $user->gares()->count() }} gare(s) affectée(s)</span>
                    @elseif($user->isControleur())
                        <span class="user-chip">Conformité documentaire</span>
                    @elseif($user->isPersonnelTsr())
                        <span class="user-chip">Espace personnel RH</span>
                    @endif
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline btn-block" type="submit">
                        <span class="icon">{!! app_icon('logout') !!}</span> Déconnexion
                    </button>
                </form>
            </div>
        </aside>

        <main class="content">
            <header class="page-header">
                <div>
                    <h1>@yield('heading', 'Tableau de bord')</h1>
                    <p>@yield('subheading', 'Progiciel intégré TSR')</p>
                </div>
                <div class="header-actions">
                    @yield('actions')
                </div>
            </header>

            <div class="page-stack">
                @include('partials.flash')
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
        }

        document.querySelectorAll('[data-gare-filter-select]').forEach(function(wrapper) {
            const filterInput = wrapper.querySelector('[data-gare-filter-input]');
            const select = wrapper.querySelector('[data-gare-filter-target]');
            if (!filterInput || !select) return;

            const allOptions = Array.from(select.querySelectorAll('option')).map(function(option) {
                return {
                    value: option.value,
                    text: option.textContent,
                    search: (option.getAttribute('data-search') || option.textContent || '').toLowerCase(),
                    selected: option.selected,
                };
            });

            function renderOptions() {
                const term = (filterInput.value || '').toLowerCase().trim();
                const selectedValue = select.value;
                select.innerHTML = '';

                allOptions.forEach(function(option) {
                    if (option.value === '') {
                        const empty = document.createElement('option');
                        empty.value = '';
                        empty.textContent = option.text;
                        select.appendChild(empty);
                        return;
                    }

                    if (term !== '' && ! option.search.includes(term)) {
                        return;
                    }

                    const el = document.createElement('option');
                    el.value = option.value;
                    el.textContent = option.text;
                    if (selectedValue && selectedValue === option.value) {
                        el.selected = true;
                    }
                    select.appendChild(el);
                });

                if (select.options.length === 1 && term !== '') {
                    const none = document.createElement('option');
                    none.value = '';
                    none.textContent = 'Aucune gare trouvée';
                    select.appendChild(none);
                }
            }

            filterInput.addEventListener('input', renderOptions);
            renderOptions();
        });

        const body = document.body;
        document.querySelectorAll('[data-menu-toggle]').forEach(function(button) {
            button.addEventListener('click', function() {
                body.classList.toggle('sidebar-open');
            });
        });
        document.querySelectorAll('[data-menu-close]').forEach(function(element) {
            element.addEventListener('click', function() {
                body.classList.remove('sidebar-open');
            });
        });

        const moduleRadios = Array.from(document.querySelectorAll('[data-user-module]'));
        const roleSelect = document.querySelector('[data-user-role]');
        const roleOptionsByModule = roleSelect ? JSON.parse(roleSelect.getAttribute('data-role-options') || '{}') : {};
        const currentRoleValue = roleSelect ? roleSelect.getAttribute('data-current-role') : null;
        const chefSection = document.querySelector('[data-chef-gare-section]');
        const multiGaresSection = document.querySelector('[data-multi-gares-section]');
        const allGaresCheckbox = document.querySelector('[data-all-gares]');
        const zoneCheckboxes = Array.from(document.querySelectorAll('[data-zone-gares]'));

        function selectedModuleValue() {
            const activeRadio = moduleRadios.find(radio => radio.checked);
            return activeRadio ? activeRadio.value : null;
        }

        function populateRoles() {
            if (! roleSelect) return;

            const module = selectedModuleValue();
            const options = roleOptionsByModule[module] || [];
            const previous = roleSelect.value || currentRoleValue;
            roleSelect.innerHTML = '';

            options.forEach(function(option) {
                const el = document.createElement('option');
                el.value = option.value;
                el.textContent = option.label;
                if (previous === option.value) {
                    el.selected = true;
                }
                roleSelect.appendChild(el);
            });

            syncUserRoleForm();
        }

        function syncUserRoleForm() {
            if (! roleSelect) return;
            const role = roleSelect.value;
            if (chefSection) {
                chefSection.hidden = ! ['chef_de_gare', 'agent_courrier_gare'].includes(role);
            }
            if (multiGaresSection) {
                multiGaresSection.hidden = ! ['caissier_gare', 'caissiere', 'chef_de_zone', 'caissier_courrier'].includes(role);
            }
        }

        function syncAllGaresState() {
            if (! allGaresCheckbox || ! zoneCheckboxes.length) return;
            zoneCheckboxes.forEach(function(checkbox) {
                checkbox.disabled = allGaresCheckbox.checked;
            });
        }

        moduleRadios.forEach(function(radio) {
            radio.addEventListener('change', populateRoles);
        });
        if (roleSelect) {
            roleSelect.addEventListener('change', syncUserRoleForm);
            populateRoles();
        }

        if (allGaresCheckbox) {
            allGaresCheckbox.addEventListener('change', syncAllGaresState);
            syncAllGaresState();
        }

        document.querySelectorAll('.table-wrapper table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(header) {
                return header.textContent.trim();
            });

            table.querySelectorAll('tbody tr').forEach(function(row) {
                Array.from(row.children).forEach(function(cell, index) {
                    if (! cell.matches('td')) return;
                    if (cell.hasAttribute('colspan')) return;
                    const label = headers[index] || '';
                    cell.setAttribute('data-label', label);
                });
            });
        });

        document.querySelectorAll('[data-copy-text]').forEach(function(button) {
            button.addEventListener('click', async function() {
                const target = button.getAttribute('data-copy-text');
                if (! target) return;

                try {
                    await navigator.clipboard.writeText(target);
                    const previous = button.innerHTML;
                    button.innerHTML = `<span class="icon">{!! app_icon('copy') !!}</span> Copié`;
                    window.setTimeout(function() {
                        button.innerHTML = previous;
                    }, 1500);
                } catch (error) {}
            });
        });
    </script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
