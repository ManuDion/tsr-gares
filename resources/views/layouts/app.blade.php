<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="theme-color" content="#8a2433">
    <meta name="description" content="Progiciel integre TSR.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TSR Finance">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @livewireStyles
</head>
<body class="app-body" data-session-timeout-ms="{{ max((int) config('session.lifetime', 30), 1) * 60 * 1000 }}">
    @php
        use App\Enums\ServiceModule;
        use App\Support\ModuleContext;

        $user = auth()->user();
        $currentModule = ModuleContext::fromRequest(request(), $user);
        $accessibleModules = collect($user->accessibleModules());
        $financialBadges = collect([ServiceModule::Gares, ServiceModule::Courrier])
            ->filter(fn (ServiceModule $module) => $user && $user->canAccessModule($module))
            ->values();
        $currentScope = $currentModule->financialScope();
        $isCashierOnlyInScope = $currentScope
            ? ($user->canActAsCashierForScope($currentScope) && ! $user->canActAsChefForScope($currentScope))
            : false;
        $isChefBlockedOnVersementInScope = false;
        if ($currentScope && $user->canActAsChefForScope($currentScope) && ! $user->canActAsCashierForScope($currentScope)) {
            $primaryGare = $user->primaryGare;
            $isChefBlockedOnVersementInScope = $primaryGare && ! $primaryGare->is_virtual && (($primaryGare->versement_mode ?? 'direct') === 'cashier');
        }
        $notificationCount = $user
            ? \App\Models\NotificationHistory::query()
                ->where('user_id', $user->id)
                ->forModule($currentModule)
                ->whereNull('read_at')
                ->count()
            : 0;

        $pendingCashierValidationsCount = 0;
        if ($user && $currentModule->supportsFinancialFlows() && $currentScope && $user->canActAsCashierForScope($currentScope)) {
            $pendingCashierValidationsCount = app(\App\Services\CashierFlowService::class)->pendingValidationsCount($user, $currentScope);
        }

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
                    <span class="icon">{!! app_icon('dashboard') !!}</span><span>Tableau de bord</span>
                </a>

                @if($currentModule->supportsFinancialFlows() && $user->canActAsCashierForScope($currentModule->financialScope()))
                    <a href="{{ route('cashier-receipts.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('cashier-receipts.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('wallet') !!}</span><span>Réceptions caissier</span>
                        @if($pendingCashierValidationsCount > 0)
                            <span class="nav-badge">{{ $pendingCashierValidationsCount }}</span>
                        @endif
                    </a>
                @endif

                @if($currentModule->supportsFinancialFlows())
                    @if($user->canSuperviseFinancialScope($currentModule->financialScope()))
                        <a href="{{ route('verifications.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('verifications.*') && !request()->routeIs('verifications.missing-entries*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('checklist') !!}</span><span>Vérification</span>
                        </a>
                        <a href="{{ route('verifications.missing-entries', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('verifications.missing-entries*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('checklist') !!}</span><span>Écritures manquantes</span>
                        </a>
                        @if($user->canAdministerModule($currentModule))
                            <a href="{{ route('bank-routing-overrides.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('bank-routing-overrides.*') ? 'active' : '' }}">
                                <span class="icon">{!! app_icon('bank') !!}</span><span>Paramétrage banque</span>
                            </a>
                        @endif
                    @endif

                    @if($isCashierOnlyInScope)
                        <span class="nav-disabled-link" title="La saisie recette chef est approuvee depuis Receptions caissier">
                            <span class="icon">{!! app_icon('wallet') !!}</span>
                            <span>{{ $currentModule === ServiceModule::Courrier ? 'Recettes courrier' : 'Recettes' }}</span>
                        </span>
                    @else
                        <a href="{{ route('recettes.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('recettes.*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('wallet') !!}</span>
                            <span>{{ $currentModule === ServiceModule::Courrier ? 'Recettes courrier' : 'Recettes' }}</span>
                        </a>
                    @endif

                    <a href="{{ route('depenses.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('depenses.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('receipt') !!}</span>
                        <span>{{ $currentModule === ServiceModule::Courrier ? 'Dépenses courrier' : 'Dépenses' }}</span>
                    </a>

                    @if($isChefBlockedOnVersementInScope)
                        <span class="nav-disabled-link" title="Votre gare est rattachee a un caissier. Le versement est effectue par le caissier.">
                            <span class="icon">{!! app_icon('bank') !!}</span>
                            <span>{{ $currentModule === ServiceModule::Courrier ? 'Versements courrier' : 'Versements' }}</span>
                        </span>
                    @else
                        <a href="{{ route('versements.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('versements.*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('bank') !!}</span>
                            <span>{{ $currentModule === ServiceModule::Courrier ? 'Versements courrier' : 'Versements' }}</span>
                        </a>
                    @endif

                    @if($currentModule === ServiceModule::Gares && $user->canAccessGaresModule() && ! $user->isChefDeGare() && ! $user->isVerificateur())
                        <a href="{{ route('gares.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('gares.*') ? 'active' : '' }}">
                            <span class="icon">{!! app_icon('train') !!}</span><span>Gares</span>
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

                @if($user->hasGlobalVisibility() && $currentModule->supportsFinancialFlows())
                    <a href="{{ route('justificatifs-batch.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('justificatifs-batch.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('document') !!}</span>
                        <span>Pieces justificatives</span>
                    </a>
                @endif

                @if($user->canManageUsersForModule($currentModule))
                    <a href="{{ route('users.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('users') !!}</span><span>Utilisateurs</span>
                    </a>
                @endif

                @if($user->canAdministerModule($currentModule))
                    <a href="{{ route('activity-logs.index', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('history') !!}</span><span>Historique système</span>
                    </a>
                @endif

                @if($currentModule->supportsFinancialFlows() && $user->canSuperviseFinancialScope($currentModule->financialScope()) && ! $user->isVerificateur())
                    <a href="{{ route('reports.performance', ['module' => $currentModule->value]) }}" class="{{ request()->routeIs('reports.performance') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('trophy') !!}</span><span>Top 5 & rapports</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->roleLabel() }} · {{ $user->moduleLabel() }}</small>
                    @if($financialBadges->isNotEmpty())
                        <div class="module-switcher module-switcher-tight">
                            @foreach($financialBadges as $badge)
                                <span class="module-chip {{ $currentModule === $badge ? 'active' : '' }}">{{ $badge->shortLabel() }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if($user->isChefDeGare() || $user->isAgentCourrierGare())
                        <span class="user-chip">{{ $user->primaryGare?->name }}</span>
                    @elseif($user->isCaissierGare() || $user->isCaissierCourrier())
                        <span class="user-chip">{{ $user->gares()->where('gares.is_virtual', false)->count() }} gare(s) affectée(s)</span>
                    @elseif($user->isControleur())
                        <span class="user-chip">Conformité documentaire</span>
                    @elseif($user->isPersonnelTsr())
                        <span class="user-chip">Espace personnel RH</span>
                    @endif
                </div>
                <form action="{{ route('logout') }}" method="POST" data-auto-logout-form>
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

    <div class="internal-viewer-modal" data-internal-viewer data-allow-download="{{ $user && $user->hasGlobalVisibility() ? '1' : '0' }}" hidden>
        <div class="internal-viewer-dialog" role="dialog" aria-modal="true" aria-label="Lecteur interne">
            <div class="internal-viewer-header">
                <strong data-internal-viewer-title>Lecteur de fichier</strong>
                <button type="button" class="btn btn-sm btn-outline" data-internal-viewer-close>Fermer</button>
            </div>
            <div class="internal-viewer-body">
                <div class="internal-viewer-state" data-internal-viewer-loading>Chargement du fichier...</div>
                <div class="internal-viewer-state" data-internal-viewer-error hidden>Impossible d'afficher ce fichier.</div>
                <iframe data-internal-viewer-frame src="" title="Lecteur interne" hidden></iframe>
                <img data-internal-viewer-image src="" alt="Apercu du fichier" hidden>
            </div>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
        }

        const sessionTimeoutMs = parseInt(document.body.getAttribute('data-session-timeout-ms') || '0', 10);
        const autoLogoutForm = document.querySelector('[data-auto-logout-form]');
        const logoutUrl = @json(route('logout'));
        const loginUrl = @json(route('login'));
        const defaultDatePlaceholder = "Choisir la date de l'opération";

        function wireDatePlaceholderInput(input) {
            if (!input || input.dataset.datePlaceholderWired === '1') {
                return;
            }

            input.dataset.datePlaceholderWired = '1';
            const placeholder = input.getAttribute('data-date-placeholder') || defaultDatePlaceholder;

            function switchToTextIfEmpty() {
                if ((input.value || '') !== '') {
                    return;
                }

                input.type = 'text';
                input.readOnly = true;
                input.placeholder = placeholder;
                input.classList.add('date-placeholder-mode');
            }

            function switchToDateInput() {
                if (input.type !== 'date') {
                    input.type = 'date';
                }
                input.readOnly = false;
                input.placeholder = '';
                input.classList.remove('date-placeholder-mode');
            }

            switchToTextIfEmpty();

            input.addEventListener('focus', function () {
                switchToDateInput();
                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                    } catch (error) {}
                }
            });

            input.addEventListener('blur', function () {
                switchToTextIfEmpty();
            });

            input.addEventListener('change', function () {
                if ((input.value || '') === '') {
                    switchToTextIfEmpty();
                } else {
                    switchToDateInput();
                }
            });
        }

        function wireDatePlaceholders(root) {
            (root || document).querySelectorAll('input[type=\"date\"], input[data-date-placeholder-wired=\"1\"]')
                .forEach(function (input) {
                    if (input.type === 'date' || input.dataset.datePlaceholderWired === '1') {
                        wireDatePlaceholderInput(input);
                    }
                });
        }

        wireDatePlaceholders(document);

        const dateInputObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches('input[type=\"date\"]')) {
                        wireDatePlaceholderInput(node);
                    } else if (node.querySelectorAll) {
                        wireDatePlaceholders(node);
                    }
                });
            });
        });

        dateInputObserver.observe(document.body, { childList: true, subtree: true });

        if (sessionTimeoutMs > 0 && autoLogoutForm) {
            let idleTimer = null;
            const activityEvents = ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'];

            function forceLogoutAfterIdle() {
                if (typeof window.fetch !== 'function') {
                    autoLogoutForm.submit();
                    return;
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                fetch(logoutUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                }).finally(function () {
                    window.location.href = loginUrl;
                });
            }

            function resetIdleTimer() {
                if (idleTimer !== null) {
                    window.clearTimeout(idleTimer);
                }
                idleTimer = window.setTimeout(forceLogoutAfterIdle, sessionTimeoutMs);
            }

            activityEvents.forEach(function (eventName) {
                window.addEventListener(eventName, resetIdleTimer, { passive: true });
            });

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    resetIdleTimer();
                }
            });

            resetIdleTimer();
        }

        document.querySelectorAll('[data-gare-autocomplete]').forEach(function(wrapper) {
            const textInput = wrapper.querySelector('input[data-gare-label]');
            const hiddenInput = wrapper.querySelector('input[data-gare-id]');
            const options = Array.from(wrapper.querySelectorAll('datalist option'));

            function syncGareId() {
                const inputValue = textInput.value.toLowerCase();
                const exact = options.find(option => option.value === textInput.value);
                const partial = options.find(option => option.value.toLowerCase().includes(inputValue));
                const match = exact || partial;
                hiddenInput.value = match ? match.dataset.id : '';
            }

            textInput.addEventListener('input', syncGareId);
            textInput.addEventListener('change', syncGareId);
            syncGareId();
        });

        const body = document.body;
        const sidebarPanel = document.querySelector('.sidebar');
        function syncSidebarScrollHint() {
            if (!sidebarPanel) return;
            const canScroll = (sidebarPanel.scrollHeight - sidebarPanel.clientHeight) > 4;
            const atEnd = (sidebarPanel.scrollTop + sidebarPanel.clientHeight) >= (sidebarPanel.scrollHeight - 4);
            sidebarPanel.classList.toggle('sidebar-can-scroll', canScroll);
            sidebarPanel.classList.toggle('sidebar-at-end', atEnd);
        }

        if (sidebarPanel) {
            sidebarPanel.addEventListener('scroll', syncSidebarScrollHint, { passive: true });
            window.addEventListener('resize', syncSidebarScrollHint, { passive: true });
            window.addEventListener('load', syncSidebarScrollHint, { passive: true });
            window.setTimeout(syncSidebarScrollHint, 0);
        }

        document.querySelectorAll('[data-menu-toggle]').forEach(function(button) {
            button.addEventListener('click', function() {
                body.classList.toggle('sidebar-open');
                window.setTimeout(syncSidebarScrollHint, 60);
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
        const cashierCollectionSection = document.querySelector('[data-cashier-collection-section]');
        const cashierCollectionModeSelect = document.querySelector('[data-cashier-collection-mode]');
        const allowMultiGareEntryCheckbox = document.querySelector('[data-allow-multi-gare-entry]');
        const allGaresCheckbox = document.querySelector('[data-all-gares]');
        const zoneCheckboxes = Array.from(document.querySelectorAll('[data-zone-gares]'));
        const supervisionLabel = document.querySelector('[data-supervision-label]');
        const supervisionLabelText = supervisionLabel ? supervisionLabel.querySelector('[data-supervision-label-text]') : null;

        function selectedModuleValue() {
            const activeRadio = moduleRadios.find(radio => radio.checked);
            return activeRadio ? activeRadio.value : '';
        }

        function populateRoles() {
            if (! roleSelect) return;

            const module = selectedModuleValue();
            const options = roleOptionsByModule[module] || roleOptionsByModule[''] || [];
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

            if (! roleSelect.value && roleSelect.options.length > 0) {
                roleSelect.options[0].selected = true;
            }

            syncUserRoleForm();
        }

        function syncUserRoleForm() {
            if (! roleSelect) return;
            const role = roleSelect.value;
            const canSelectPrimaryGare = ['chef_de_gare', 'agent_courrier_gare'].includes(role);
            const canSelectMultipleGaresByRole = ['caissier_gare', 'caissiere', 'chef_de_zone', 'caissier_courrier', 'verificateur'].includes(role);
            const isCashierRole = ['caissier_gare', 'caissiere', 'chef_de_zone', 'caissier_courrier'].includes(role);
            const canSelectMultipleGares = canSelectMultipleGaresByRole || (allowMultiGareEntryCheckbox && allowMultiGareEntryCheckbox.checked);

            if (chefSection) {
                chefSection.hidden = ! canSelectPrimaryGare;
            }
            if (multiGaresSection) {
                multiGaresSection.hidden = ! canSelectMultipleGares;
            }
            if (cashierCollectionSection) {
                cashierCollectionSection.hidden = ! isCashierRole;
            }
            if (cashierCollectionModeSelect) {
                cashierCollectionModeSelect.disabled = ! isCashierRole;
            }

            updateSupervisionLabel(role);
        }

        function syncAllGaresState() {
            if (! allGaresCheckbox || ! zoneCheckboxes.length) return;
            zoneCheckboxes.forEach(function(checkbox) {
                checkbox.disabled = allGaresCheckbox.checked;
            });
            updateSupervisionLabel(roleSelect ? roleSelect.value : '');
        }

        function selectedZoneGareCount() {
            if (allGaresCheckbox && allGaresCheckbox.checked) {
                return zoneCheckboxes.length;
            }

            return zoneCheckboxes.filter(function(checkbox) {
                return checkbox.checked;
            }).length;
        }

        function updateSupervisionLabel(role) {
            if (! supervisionLabel || ! supervisionLabelText) return;

            if (['admin', 'responsable'].includes(role)) {
                supervisionLabel.hidden = false;
                supervisionLabelText.textContent = 'Superviseur universel';
                return;
            }

            if (['admin_gares', 'admin_courrier', 'admin_documents', 'admin_rh'].includes(role)) {
                supervisionLabel.hidden = false;
                supervisionLabelText.textContent = 'Administrateur de service';
                return;
            }

            if (role === 'verificateur') {
                supervisionLabel.hidden = false;
                const count = selectedZoneGareCount();
                supervisionLabelText.textContent = `Superviseur limite a ${count} gare(s)`;
                return;
            }

            supervisionLabel.hidden = true;
        }

        moduleRadios.forEach(function(radio) {
            radio.addEventListener('change', populateRoles);
        });
        if (roleSelect) {
            roleSelect.addEventListener('change', syncUserRoleForm);
            populateRoles();
        }

        if (allowMultiGareEntryCheckbox) {
            allowMultiGareEntryCheckbox.addEventListener('change', function() {
                syncUserRoleForm();
                syncAllGaresState();
            });
        }

        if (allGaresCheckbox) {
            allGaresCheckbox.addEventListener('change', syncAllGaresState);
            syncAllGaresState();
        }

        zoneCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateSupervisionLabel(roleSelect ? roleSelect.value : '');
            });
        });

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

        document.querySelectorAll('[data-width-percent]').forEach(function(element) {
            const parsed = Number.parseFloat(element.getAttribute('data-width-percent') || '0');
            const value = Number.isFinite(parsed) ? parsed : 0;
            const clamped = Math.max(0, Math.min(100, value));
            element.style.width = clamped + '%';
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

        const internalViewerModal = document.querySelector('[data-internal-viewer]');
        const internalViewerFrame = internalViewerModal ? internalViewerModal.querySelector('[data-internal-viewer-frame]') : null;
        const internalViewerImage = internalViewerModal ? internalViewerModal.querySelector('[data-internal-viewer-image]') : null;
        const internalViewerTitle = internalViewerModal ? internalViewerModal.querySelector('[data-internal-viewer-title]') : null;
        const internalViewerLoading = internalViewerModal ? internalViewerModal.querySelector('[data-internal-viewer-loading]') : null;
        const internalViewerError = internalViewerModal ? internalViewerModal.querySelector('[data-internal-viewer-error]') : null;
        const internalViewerAllowsDownload = internalViewerModal
            ? internalViewerModal.getAttribute('data-allow-download') === '1'
            : false;
        let internalViewerBlobUrl = null;

        function toggleViewerNode(node, visible) {
            if (!node) return;
            node.hidden = !visible;
            node.style.display = visible ? '' : 'none';
        }

        function viewerShowLoading() {
            toggleViewerNode(internalViewerLoading, true);
            toggleViewerNode(internalViewerError, false);
            if (internalViewerFrame) {
                toggleViewerNode(internalViewerFrame, false);
                internalViewerFrame.src = '';
            }
            if (internalViewerImage) {
                toggleViewerNode(internalViewerImage, false);
                internalViewerImage.src = '';
            }
        }

        function viewerShowError(message) {
            toggleViewerNode(internalViewerLoading, false);
            if (internalViewerError) {
                internalViewerError.textContent = message || "Impossible d'afficher ce fichier.";
                toggleViewerNode(internalViewerError, true);
            }
            if (internalViewerFrame) {
                toggleViewerNode(internalViewerFrame, false);
                internalViewerFrame.src = '';
            }
            if (internalViewerImage) {
                toggleViewerNode(internalViewerImage, false);
                internalViewerImage.src = '';
            }
        }

        function clearViewerBlobUrl() {
            if (internalViewerBlobUrl) {
                URL.revokeObjectURL(internalViewerBlobUrl);
                internalViewerBlobUrl = null;
            }
        }

        function fileExtensionFromName(name) {
            const value = String(name || '').trim().toLowerCase();
            if (!value || value.indexOf('.') === -1) return '';
            return value.split('.').pop() || '';
        }

        function extractFileNameFromDisposition(value) {
            const header = String(value || '');
            if (!header) return '';

            const utf8Match = header.match(/filename\\*=UTF-8''([^;]+)/i);
            if (utf8Match && utf8Match[1]) {
                try {
                    return decodeURIComponent(utf8Match[1].replace(/["']/g, '').trim());
                } catch (error) {
                    return utf8Match[1].replace(/["']/g, '').trim();
                }
            }

            const simpleMatch = header.match(/filename=([^;]+)/i);
            if (simpleMatch && simpleMatch[1]) {
                return simpleMatch[1].replace(/["']/g, '').trim();
            }

            return '';
        }

        function detectViewerCategory(mimeType, fileName) {
            const type = String(mimeType || '').toLowerCase();
            const ext = fileExtensionFromName(fileName);

            if (type.startsWith('image/')) return 'image';
            if (type === 'application/pdf') return 'pdf';

            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'].includes(ext)) return 'image';
            if (ext === 'pdf') return 'pdf';

            return 'unknown';
        }

        function buildPdfViewerUrl(fileUrl) {
            const baseUrl = String(fileUrl || '');
            if (internalViewerAllowsDownload) {
                return baseUrl;
            }
            if (baseUrl.includes('#')) {
                return baseUrl;
            }

            return baseUrl + '#toolbar=0&navpanes=0&scrollbar=1';
        }

        async function openInternalFileViewerFromLink(link) {
            if (!internalViewerModal || !internalViewerFrame || !link) return false;

            const fileUrl = link.getAttribute('href') || '';
            if (!fileUrl) return false;

            viewerShowLoading();
            internalViewerModal.hidden = false;
            document.body.classList.add('modal-open');
            if (!internalViewerAllowsDownload) {
                document.body.classList.add('viewer-print-block');
            }
            if (internalViewerTitle) {
                internalViewerTitle.textContent = link.getAttribute('data-file-title') || 'Lecteur de fichier';
            }

            clearViewerBlobUrl();

            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error('HTTP '+response.status);
                }

                const mimeType = (response.headers.get('content-type') || '').toLowerCase();
                const responseFileName = response.headers.get('x-file-name')
                    || extractFileNameFromDisposition(response.headers.get('content-disposition'))
                    || link.getAttribute('data-file-title')
                    || '';
                const category = detectViewerCategory(mimeType, responseFileName);

                toggleViewerNode(internalViewerLoading, false);
                toggleViewerNode(internalViewerError, false);

                if (category === 'pdf') {
                    toggleViewerNode(internalViewerFrame, true);
                    internalViewerFrame.src = buildPdfViewerUrl(fileUrl);
                    if (internalViewerImage) {
                        toggleViewerNode(internalViewerImage, false);
                        internalViewerImage.src = '';
                    }
                } else {
                    const blob = await response.blob();
                    internalViewerBlobUrl = URL.createObjectURL(blob);

                    if (category === 'image') {
                        if (internalViewerImage) {
                            internalViewerImage.src = internalViewerBlobUrl;
                            toggleViewerNode(internalViewerImage, true);
                        }
                        toggleViewerNode(internalViewerFrame, false);
                        internalViewerFrame.src = '';
                    } else if (category === 'unknown') {
                        const viewerUrl = internalViewerAllowsDownload
                            ? internalViewerBlobUrl
                            : internalViewerBlobUrl + '#toolbar=0&navpanes=0&scrollbar=1';
                        internalViewerFrame.src = viewerUrl;
                        toggleViewerNode(internalViewerFrame, true);
                        if (internalViewerImage) {
                            toggleViewerNode(internalViewerImage, false);
                            internalViewerImage.src = '';
                        }
                    } else {
                        viewerShowError("Type de fichier non pris en charge par le lecteur.");
                    }
                }
            } catch (error) {
                // Fallback robuste pour PDF: tentative d'ouverture directe en iframe.
                const fallbackCategory = detectViewerCategory('', link.getAttribute('data-file-title') || fileUrl);
                if (fallbackCategory === 'pdf') {
                    toggleViewerNode(internalViewerLoading, false);
                    toggleViewerNode(internalViewerError, false);
                    toggleViewerNode(internalViewerFrame, true);
                    internalViewerFrame.src = buildPdfViewerUrl(fileUrl);
                    if (internalViewerImage) {
                        toggleViewerNode(internalViewerImage, false);
                        internalViewerImage.src = '';
                    }
                    return false;
                } else {
                    viewerShowError("Lecture impossible. Verifiez vos droits ou le format du fichier.");
                }
            }

            return false;
        }

        window.openInternalFileViewer = function(link) {
            if (!link) return false;
            openInternalFileViewerFromLink(link);
            return false;
        };

        function closeInternalViewer() {
            if (!internalViewerModal || !internalViewerFrame) return;
            internalViewerModal.hidden = true;
            clearViewerBlobUrl();
            toggleViewerNode(internalViewerLoading, false);
            toggleViewerNode(internalViewerError, false);
            internalViewerFrame.src = '';
            toggleViewerNode(internalViewerFrame, false);
            if (internalViewerImage) {
                internalViewerImage.src = '';
                toggleViewerNode(internalViewerImage, false);
            }
            document.body.classList.remove('modal-open');
            document.body.classList.remove('viewer-print-block');
        }

        if (internalViewerModal && internalViewerFrame) {
            document.addEventListener('click', function(event) {
                const opener = event.target.closest('[data-internal-file-preview]');
                if (opener) {
                    event.preventDefault();
                    openInternalFileViewerFromLink(opener);
                    return;
                }

                const closer = event.target.closest('[data-internal-viewer-close]');
                if (closer || event.target === internalViewerModal) {
                    closeInternalViewer();
                }
            });

            document.addEventListener('keydown', function(event) {
                const isPrintShortcut = (event.ctrlKey || event.metaKey) && (event.key === 'p' || event.key === 'P');
                if (isPrintShortcut && !internalViewerModal.hidden && !internalViewerAllowsDownload) {
                    event.preventDefault();
                    event.stopPropagation();
                    viewerShowError("Impression non autorisee pour votre profil.");
                    return;
                }

                if (event.key === 'Escape' && !internalViewerModal.hidden) {
                    closeInternalViewer();
                }
            });

            const internalViewerBody = internalViewerModal.querySelector('.internal-viewer-body');
            if (internalViewerBody) {
                internalViewerBody.addEventListener('contextmenu', function(event) {
                    if (!internalViewerAllowsDownload && !internalViewerModal.hidden) {
                        event.preventDefault();
                    }
                });
            }
        }
    </script>
    @stack('scripts')
    @livewireScripts
</body>
</html>



