<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · TSR Gares Finance</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-layout">
        <section class="auth-hero">
            <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire" class="auth-logo auth-logo-large">
            <div class="auth-hero-copy">
                <span class="auth-badge">Plateforme sécurisée</span>
                <h1>Bienvenue sur TSR Gares Finance</h1>
                <p>Centralisez les recettes, dépenses, versements bancaires, justificatifs et contrôles journaliers dans une interface moderne pensée pour les équipes terrain et les superviseurs.</p>
                <ul class="auth-points">
                    <li>Dashboard adapté selon le rôle utilisateur</li>
                    <li>Contrôles journaliers et notifications ciblées</li>
                    <li>Versements avec OCR et validation humaine</li>
                </ul>
            </div>
        </section>

        <section class="auth-card auth-card-modern">
            <div class="auth-card-head">
                <h2>Connexion</h2>
                <p>Accédez à votre espace de gestion financière.</p>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('login.store') }}" class="stack-md">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="exemple@tsr.test">
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

            <div class="demo-box">
                <strong>Comptes de démonstration</strong>
                <small>admin@tsr.test / password</small>
                <small>responsable@tsr.test / password</small>
                <small>chef.gare@tsr.test / password</small>
                <small>caissiere@tsr.test / password</small>
            </div>
        </section>
    </div>
</body>
</html>
