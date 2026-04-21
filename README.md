# Progiciel TSR — mise à jour production V4.1

Application Laravel 12 pour TSR, structurée par **services / modules métiers** et prête pour la mise en production.

## Services / modules pris en charge

- **Service de gestion des gares**
- **Service de gestion des documents**
- **Service courrier**
- **Service RH** (socle préparatoire)

Les rôles **Administrateur** et **Responsable** restent universels.

## Correctifs et évolutions de cette version

### Tableau de bord
- dashboard **gares** et **courrier** avec :
  - total recettes
  - total dépenses
  - total versements
  - alertes métier
  - notifications récentes
  - **évolution comparative par semaine S1 à S4** sous forme de graphique
- pour le **service courrier**, la recette est considérée comme **unique** et non ventilée

### Recettes
- **service gares** :
  - 4 postes de recette :
    - ventes tickets inter
    - ventes tickets national
    - transport bagages inter
    - transport bagages national
  - **montant total calculé automatiquement**
- **service courrier** :
  - recette **unique**
  - un seul champ montant
- justificatif possible en PDF / image / photo mobile
- si le nom du justificatif n’est pas renseigné, le système utilise automatiquement :
  - `Module_Gare_DateOperation`

### Dépenses
- saisie multiple maintenue
- rôle **Agent courrier gare** : gare imposée par la connexion
- rôle **Caissier courrier** : sélection filtrée parmi les gares affectées
- justificatif possible en PDF / image / photo mobile
- si le nom du justificatif n’est pas renseigné, le système utilise automatiquement :
  - `Module_Gare_DateOperation`

### Versements
- saisie manuelle simple
- rôle **Agent courrier gare** : gare imposée par la connexion
- rôle **Caissier courrier** : sélection filtrée parmi les gares affectées
- import PDF / image / photo mobile
- si le nom du bordereau n’est pas renseigné, le système utilise automatiquement :
  - `Module_Gare_DateOperation`

### Gestion des utilisateurs
- correction de la suppression d’utilisateur
- prise en compte des derniers ajustements du `UserController`
- rôles et accès conservés selon le module/service

### Langue et messages
- configuration prévue pour le **français**
- fichier de validation Laravel en français ajouté
- messages d’erreur plus lisibles

### Correctif technique important
- correction de l’erreur :
  - `preg_replace(): Unknown modifier '\'`
- correction de la génération de nom de fichier personnalisé

## Affectation des gares selon les rôles

### Service de gestion des gares
- **Chef de gare** : gare imposée
- **Caissier gare** : choix parmi les gares affectées

### Service courrier
- **Agent courrier gare** : gare imposée
- **Caissier courrier** : choix parmi les gares affectées

## Menus et création
- **Admin** et **Responsable** peuvent consulter largement mais ne doivent pas être utilisés comme profils de saisie terrain
- les boutons de création dépendent désormais des **permissions réelles de saisie**
- si un rôle ne peut pas saisir, le bouton de création n’apparaît pas

## Justificatifs mobiles
Les formulaires de recettes, dépenses et versements acceptent :
- PDF
- JPG / JPEG
- PNG
- photo mobile via caméra

> Remarque : le recadrage dépend du navigateur / téléphone utilisé. Sur beaucoup de smartphones, l’interface native permet déjà d’ajuster l’image avant validation.

## Fichiers de langue
Cette version ajoute :
- `lang/fr/validation.php`

Pensez à utiliser dans votre `.env` :

```env
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
```

## Installation / mise à jour

### 1. Dépendances
```bash
composer install
composer dump-autoload
```

### 2. Environnement
Copier le fichier d’environnement si nécessaire :

```bash
cp .env.example .env
```

Configurer ensuite :
- la base MySQL
- `APP_LOCALE=fr`
- `APP_FALLBACK_LOCALE=fr`

### 3. Base de données
Si la base est déjà en place :

```bash
php artisan migrate
```

### 4. Nettoyage cache
```bash
php artisan optimize:clear
php artisan view:clear
```

### 5. Lancement
```bash
php artisan serve
```

## Points de contrôle après mise à jour

Tester en priorité :

1. création / modification d’une recette **gares**
2. création / modification d’une recette **courrier**
3. calcul automatique du **montant total**
4. saisie d’une dépense avec justificatif sur téléphone
5. saisie d’un versement avec bordereau PDF / image
6. sélection de gare :
   - imposée pour chef de gare / agent courrier gare
   - filtrée pour caissier gare / caissier courrier
7. suppression d’un utilisateur
8. affichage du dashboard avec graphique **S1 à S4**

## Structure métier actuelle

### Service de gestion des gares
- recettes
- dépenses
- versements
- vérifications
- rapports
- notifications

### Service courrier
- même logique que le service gares
- mais périmètre fonctionnel séparé

### Service de gestion des documents
- documents administratifs
- échéances
- rappels

### Service RH
- socle de préparation
- base de données prête pour les futurs workflows du personnel

## Prochaine étape recommandée
Après validation de cette version, la suite logique sera :
- consolidation fonctionnelle complète
- stabilisation production
- puis enrichissement du **module RH** selon les flows détaillés fournis par TSR
