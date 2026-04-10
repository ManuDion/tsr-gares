# Correctif Blade / artisan serve

## Erreur corrigée
`The first parameter of the given Closure is missing a type hint.`

## Cause
Un enregistrement `Blade::stringable()` avait été ajouté sans type-hint sur le premier paramètre.
Laravel 12 refuse ce cas au démarrage de l'application.

## Correctifs appliqués
- suppression de `Blade::stringable(...)` dans `app/Providers/AppServiceProvider.php`
- externalisation du helper `app_icon()` dans `app/Support/helpers.php`
- ajout de `app/Support/helpers.php` dans l'autoload Composer

## Commandes à lancer
```bash
composer dump-autoload
php artisan optimize:clear
php artisan serve
```
