<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="theme-color" content="#8a2433">
    <meta name="description" content="Application TSR Côte d'Ivoire de gestion financière multi-gares.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @livewireStyles
</head>
<body>
    @php($user = auth()->user())
    @php($notificationCount = $user ? \App\Models\NotificationHistory::query()->when(! $user->canViewAllGares(), fn ($query) => $query->where('user_id', $user->id))->whereNull('read_at')->count() : 0)

    <div class="mobile-topbar">
        <button class="menu-toggle" type="button" data-menu-toggle>
            <span class="icon">{!! app_icon('menu') !!}</span>
            Menu
        </button>
        <strong>TSR Gares Finance</strong>
    </div>

    <div class="app-overlay" data-menu-close></div>

    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire">
                <div>
                    <strong>TSR Gares Finance</strong>
                    <small>Laravel 12 · MySQL</small>
                </div>
            </div>

            <nav class="nav-links">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('dashboard') !!}</span><span>Dashboard</span>
                </a>

                <a href="{{ route('recettes.index') }}" class="{{ request()->routeIs('recettes.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('wallet') !!}</span><span>Recettes</span>
                </a>

                <a href="{{ route('depenses.index') }}" class="{{ request()->routeIs('depenses.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('receipt') !!}</span><span>Dépenses</span>
                </a>

                <a href="{{ route('versements.index') }}" class="{{ request()->routeIs('versements.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('bank') !!}</span><span>Versements</span>
                </a>

                <a href="{{ route('gares.index') }}" class="{{ request()->routeIs('gares.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('train') !!}</span><span>Gares</span>
                </a>

                <a href="{{ route('notifications.index') }}" class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <span class="icon">{!! app_icon('bell') !!}</span>
                    <span>Notifications</span>
                    @if($notificationCount > 0)
                        <span class="nav-badge">{{ $notificationCount }}</span>
                    @endif
                </a>

                @if($user->isAdmin())
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('users') !!}</span><span>Utilisateurs</span>
                    </a>
                @endif

                @if($user->isAdmin() || $user->isResponsable())
                    <a href="{{ route('reports.performance') }}" class="{{ request()->routeIs('reports.performance') ? 'active' : '' }}">
                        <span class="icon">{!! app_icon('trophy') !!}</span><span>Top 5 & rapports</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->roleLabel() }}</small>
                    @if($user->isChefDeGare())
                        <span class="user-chip">{{ $user->primaryGare?->name }}</span>
                    @elseif($user->isCaissiere())
                        <span class="user-chip">{{ $user->gares()->count() }} gare(s) affectée(s)</span>
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
                    <p>@yield('subheading', "Suivi financier des gares TSR Côte d'Ivoire")</p>
                </div>
                <div class="header-actions">
                    @yield('actions')
                </div>
            </header>

            @include('partials.flash')

            @yield('content')
        </main>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
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

        const roleSelect = document.querySelector('[data-user-role]');
        const chefSection = document.querySelector('[data-chef-gare-section]');
        const caissiereSection = document.querySelector('[data-caissiere-section]');
        const allGaresCheckbox = document.querySelector('[data-all-gares]');
        const zoneSelect = document.querySelector('[data-zone-gares]');

        function syncUserRoleForm() {
            if (! roleSelect) return;
            const role = roleSelect.value;
            if (chefSection) {
                chefSection.hidden = role !== 'chef_de_gare';
            }
            if (caissiereSection) {
                caissiereSection.hidden = role !== 'caissiere';
            }
        }

        function syncAllGaresState() {
            if (! allGaresCheckbox || ! zoneSelect) return;
            zoneSelect.disabled = allGaresCheckbox.checked;
        }

        if (roleSelect) {
            roleSelect.addEventListener('change', syncUserRoleForm);
            syncUserRoleForm();
        }

        if (allGaresCheckbox) {
            allGaresCheckbox.addEventListener('change', syncAllGaresState);
            syncAllGaresState();
        }
    </script>
    @livewireScripts
</body>
</html>
