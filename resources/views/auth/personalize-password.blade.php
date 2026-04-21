<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personnalisation du mot de passe · TSR</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-layout auth-layout-single">
        <section class="auth-card auth-card-modern">
            <div class="auth-card-head">
                <h2>Personnalisation du mot de passe</h2>
                <p>Pour sécuriser votre compte, personnalisez votre mot de passe avant d'accéder à l'application.</p>
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
</body>
</html>
