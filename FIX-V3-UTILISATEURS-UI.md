# Correctifs V3

- Typologie des utilisateurs alignée :
  - Administrateur : supervision globale + gestion utilisateurs/gares
  - Responsable : supervision globale sans saisie
  - Chef de gare : saisie sur sa gare uniquement
  - Caissière : saisie sur gares affectées uniquement
- Menus adaptés selon le profil
- Champ de gare filtré avec saisie assistée
- Bouton filtre sur le dashboard
- Icônes et interface modernisées
- Menu responsive mobile / tablette / desktop
- Rapport Top 5 réservé admin et responsable
- Dashboard enrichi avec courbes et indicateurs visuels

## Important
Pour voir les nouveaux rôles et données de démonstration, relancer :

php artisan migrate:fresh --seed
