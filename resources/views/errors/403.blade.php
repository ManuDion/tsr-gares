<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Accès refusé</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>Accès refusé</h1>
        <p>Vous n'avez pas les autorisations nécessaires pour consulter cette ressource.</p>
        <a class="btn btn-primary btn-block" href="{{ route('dashboard') }}">Retour au dashboard</a>
    </div>
</body>
</html>
