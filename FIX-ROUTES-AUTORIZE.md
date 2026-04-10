# Correctif accès modules / authorize()

## Problème corrigé
Les écrans `recettes`, `gares`, `dépenses`, `versements`, `utilisateurs` et `notifications`
appelaient la méthode `$this->authorize(...)` dans leurs contrôleurs.

Le contrôleur de base `app/Http/Controllers/Controller.php` n'étendait pas le contrôleur Laravel
et n'utilisait pas le trait `AuthorizesRequests`, ce qui provoquait l'erreur :

`Call to undefined method App\Http\Controllers\...::authorize()`

## Correction appliquée
Le contrôleur de base a été remplacé par une version conforme Laravel :

- extension de `Illuminate\Routing\Controller`
- ajout du trait `AuthorizesRequests`
- ajout du trait `ValidatesRequests`

## Après mise à jour
Sur votre machine, remplacez simplement les fichiers du projet puis exécutez :

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan serve
```

Si nécessaire, vous pouvez aussi supprimer `vendor/` et relancer `composer install`.
