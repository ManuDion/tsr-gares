<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personnalisation du mot de passe - TSR</title>
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
    <div class="auth-layout auth-layout-single">
        <section class="auth-card auth-card-modern">
            <div class="auth-card-head">
                <h2>Personnalisation du mot de passe</h2>
                <p>Pour securiser votre compte, personnalisez votre mot de passe avant d'acceder a l'application.</p>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('password.personalize.update') }}" class="stack-md">
                @csrf
                @method('PUT')

                <div>
                    <label>Mot de passe actuel</label>
                    <input type="password" name="current_password" required>
                    @error('current_password')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div>
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" required>
                    @error('password')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div>
                    <label>Confirmation du mot de passe</label>
                    <input type="password" name="password_confirmation" required>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Enregistrer mon mot de passe</button>
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
