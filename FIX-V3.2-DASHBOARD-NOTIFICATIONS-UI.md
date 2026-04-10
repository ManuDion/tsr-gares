# Correctifs V3.2

Cette version corrige et améliore :

- dashboard adapté au rôle utilisateur
- formulaire utilisateur conditionnel sur le rôle Caissière
- activation / désactivation immédiate des gares et utilisateurs
- retour arrière sur la fiche gare
- lecture et téléchargement des justificatifs
- notifications visibles et recalculées automatiquement
- interface modernisée avec couleurs, cartes, icônes et responsive renforcé

## Après mise à jour

Exécuter :

```bash
composer dump-autoload
php artisan optimize:clear
php artisan serve
```

Pour repartir de zéro avec les données de démonstration :

```bash
php artisan migrate:fresh --seed
php artisan serve
```

## Notification de contrôle journalier

Le contrôle journalier est toujours planifié à 10h00 (Africa/Abidjan), mais l'application recalcule aussi le contrôle du jour précédent à l'ouverture du dashboard et de la page notifications.
