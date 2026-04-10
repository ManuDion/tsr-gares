<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hors ligne · TSR Gares Finance</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-card">
        <img src="{{ asset('assets/logo-tsr.jpg') }}" alt="TSR Côte d'Ivoire" class="auth-logo">
        <h1>Mode hors ligne</h1>
        <p>La connexion réseau semble indisponible. Reconnectez-vous pour reprendre la synchronisation.</p>
        <a class="btn btn-primary btn-block" href="{{ route('dashboard') }}">Réessayer</a>
    </div>
</body>
</html>
