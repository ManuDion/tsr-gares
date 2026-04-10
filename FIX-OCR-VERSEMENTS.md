# Itération OCR versements

Cette itération ajoute :

- lecture OCR locale réelle des bordereaux,
- conversion PDF vers image avant OCR,
- parcours en 2 étapes : analyse puis validation,
- nouveau champ `date de la recette`,
- modifications de versements avec verrouillage 48h / déverrouillage superviseur,
- historique de modification des versements,
- conservation et lecture des justificatifs.

## Commandes après mise à jour

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan serve
```
