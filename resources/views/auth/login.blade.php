<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - TSR</title>
    <meta name="theme-color" content="#8a2433">
    <meta name="description" content="Plateforme de gestion TSR.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TSR Finance">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-layout">
        <section class="auth-hero">
            <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Cote d'Ivoire" class="auth-logo auth-logo-large">
            <div class="auth-hero-copy">
                <span class="auth-badge">Plateforme securisee</span>
                <h1>Bienvenue sur la plateforme TSR</h1>
                <p>Accedez a votre espace de travail pour gerer les operations des gares, les documents justificatifs, les controles journaliers et les echanges internes depuis une interface unique.</p>
                <ul class="auth-points">
                    <li>Acces personnalise selon le role utilisateur</li>
                    <li>Suivi financier, documentaire et notifications metier</li>
                    <li>Interface pensee pour le mobile, la tablette et le poste fixe</li>
                </ul>
            </div>
        </section>

        <section class="auth-card auth-card-modern">
            <div class="auth-card-head">
                <h2>Connexion</h2>
                <p>Renseignez vos identifiants de production pour acceder a votre espace.</p>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('login.store') }}" class="stack-md">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="utilisateur@tsr.ci">
                </div>

                <div>
                    <label>Mot de passe</label>
                    <input type="password" name="password" required placeholder="Votre mot de passe">
                </div>

                <label class="checkbox-line">
                    <input type="checkbox" name="remember">
                    <span>Se souvenir de moi</span>
                </label>

                <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
            </form>
        </section>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
        }
    </script>
</body>
</html>
