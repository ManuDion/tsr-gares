<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · TSR Gares Finance</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-card">
        <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire" class="auth-logo">
        <h1>Connexion</h1>
        <p>Accédez à la plateforme de gestion financière multi-gares.</p>

        @include('partials.flash')

        <form method="POST" action="{{ route('login.store') }}" class="stack-md">
            @csrf
            <div>
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div>
                <label>Mot de passe</label>
                <input type="password" name="password" required>
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
            <small>chef.zone@tsr.test / password</small>
        </div>
    </div>
</body>
</html>
