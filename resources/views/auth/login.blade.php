<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · TSR</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-layout">
        <section class="auth-hero">
            <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire" class="auth-logo auth-logo-large">
            <div class="auth-hero-copy">
                <span class="auth-badge">Plateforme sécurisée</span>
                <h1>Bienvenue sur la plateforme TSR</h1>
                <p>Accédez à votre espace de travail pour gérer les opérations des gares, les documents justificatifs, les contrôles journaliers et les échanges internes depuis une interface unique.</p>
                <ul class="auth-points">
                    <li>Accès personnalisé selon le rôle utilisateur</li>
                    <li>Suivi financier, documentaire et notifications métier</li>
                    <li>Interface pensée pour le mobile, la tablette et le poste fixe</li>
                </ul>
            </div>
        </section>

        <section class="auth-card auth-card-modern">
            <div class="auth-card-head">
                <h2>Connexion</h2>
                <p>Renseignez vos identifiants de production pour accéder à votre espace.</p>
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
</body>
</html>
