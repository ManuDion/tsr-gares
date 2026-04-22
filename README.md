# Progiciel TSR - README complet et a jour

Application Laravel 12 pour la gestion TSR, organisee par modules metiers.

## 1) Vue d'ensemble

Le progiciel est structure autour de 4 modules:

- Gares
- Documents
- Courrier
- RH

Les roles `admin` et `responsable` sont universels (vision globale).
Les autres roles sont limites a leur module metier.

## 2) Fonctionnalites principales

### 2.1 Module Gares

- Saisie des recettes
- Saisie des depenses (multi-lignes)
- Saisie des versements avec justificatif
- Controle des ecarts / verifications
- Dashboard financier
- Exports

### 2.2 Module Courrier

- Meme moteur fonctionnel que Gares, isole via `service_scope = courrier`
- Recettes
- Depenses
- Versements
- Verifications
- Dashboard courrier

### 2.3 Module Documents

- Gestion des documents administratifs
- Echeances et suivi de conformite
- Notifications documentaires

### 2.4 Module RH (socle)

- Dossier agent
- Pieces RH
- Affectations (base prete pour evolution)
- Comptes et statut d'activation

## 3) Gestion des utilisateurs et roles

### 3.1 Roles universels

- Administrateur (`admin`)
- Responsable (`responsable`)

### 3.2 Roles metier

- Verificateur (`verificateur`) - supervise un module financier sur une ou plusieurs gares
- Chef de gare (`chef_de_gare`)
- Caissier gare (`caissier_gare`)
- Controleur (`controleur`)
- Agent courrier gare (`agent_courrier_gare`)
- Caissier courrier (`caissier_courrier`)
- Responsable RH (`responsable_rh`)
- Personnel TSR (`personnel_tsr`)

Notes:

- `caissiere` et `chef_de_zone` sont geres comme aliases historiques de `caissier_gare`.
- Les roles `admin` et `responsable` peuvent etre crees sans module/service.

### 3.3 Creation utilisateur (formulaire)

Le formulaire supporte:

- Choix du module principal (ou aucun pour les superviseurs universels)
- Filtrage automatique des roles selon le module
- Affectation gare principale pour certains roles
- Affectation multi-gares (dont verificateur)
- Option "toutes les gares"
- Libelle visuel de supervision:
  - `Superviseur universel`
  - `Superviseur limite a X gare(s)`

### 3.4 Connexion et securite

- `must_change_password` impose la personnalisation du mot de passe a la premiere connexion.

## 4) Chat interne

Le chat supporte 3 types de conversation:

- `general`: canal global pour tous les utilisateurs actifs
- `service_internal`: canal interne a un seul service/module
- `direct`: conversation B2B entre 2 utilisateurs

Comportements:

- Bouton `Nouvelle conversation` vers un ecran dedie
- Mode direct avec champ de recherche/filtre interlocuteur (auto-completion)
- Reutilisation d'une conversation directe existante si deja presente
- Pruning automatique des conversations directes inactives

## 5) Dashboard et reporting

### 5.1 Evolution des montants

Sur dashboards Gares/Courrier:

- `Evolution des montants` en courbe (graphique)
- Granularite jour par jour du 1 au 31
- Tableau en dessous conserve en comparatif hebdomadaire `S1` a `S4`

### 5.2 Filtre gare sans duplication

Quand une gare est selectionnee:

- les donnees globales ne sont plus dupliquees
- seules les informations de la gare ciblee restent affichees

### 5.3 Detail recettes par gare

- Bloc `Detail des types de recettes par gare` limite au Top 5

### 5.4 Particularite module Courrier

- La recette courrier est traitee comme un type unique (non desagrege comme Gares)

## 6) Notifications et historique systeme

- Les notifications sont filtrees par module actif
- L'historique systeme est filtre par module actif
- En pratique, un utilisateur dans un module ne voit que les elements pertinents de ce module

## 7) OCR versements

Le pre-remplissage OCR des versements est retire dans cette version de production.
Parcours actuel:

1. Upload PDF / image justificative
2. Saisie manuelle
3. Enregistrement

## 8) Base de donnees - points importants

- `service_scope` sur flux financiers et verifications
- Tables socle RH: `employees`, `employee_assignments`, `employee_documents`, etc.
- Chat:
  - `conversation_type` (`general`, `service_internal`, `direct`)
  - `service_module` sur conversations de service

Migrations recentes a verifier:

- `2026_05_10_090000_refonte_services_and_rh_foundation.php`
- `2026_05_11_120000_add_conversation_type_to_conversations_table.php`
- `2026_05_11_130000_add_service_module_to_conversations_table.php`

## 9) Installation locale

### Prerequis

- PHP 8.2+
- Composer
- MySQL ou MariaDB
- Extensions PHP requises par Laravel

### Etapes

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan optimize:clear
```

### Seeder administrateur

Configurer dans `.env`:

```env
APP_ADMIN_NAME="Administrateur TSR"
APP_ADMIN_PHONE="+2250000000000"
APP_ADMIN_EMAIL="admin@votredomaine.com"
APP_ADMIN_PASSWORD="MotDePasseFort123!"
```

Puis:

```bash
php artisan db:seed
```

### Demarrage

```bash
php artisan serve
```

## 10) Tests et verification

Tests:

```bash
php artisan test
```

Nettoyage cache:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

## 11) Deploiement production

`.env`:

```env
APP_ENV=production
APP_DEBUG=false
```

Commandes:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

## 12) Changelog recent (mise a niveau)

- Chat aligne sur 3 modalites: `general`, `service_internal`, `direct`
- Ecran dedie `Nouvelle conversation`
- Selection interlocuteur direct via filtre/auto-completion
- Notifications et historique systeme filtres par module
- Dashboard Gares/Courrier: courbe evolutive jour par jour (1 a 31)
- Comparatif hebdomadaire conserve (S1 a S4)
- Correction duplication des blocs globaux lors du filtre sur une gare
- `Detail des types de recettes par gare` en Top 5
- Recettes Courrier en type unique
- Formulaire utilisateur:
  - verificateur avec choix des gares
  - affichage `Superviseur universel` / `Superviseur limite a X gare(s)`
  - possibilite de creer `admin`/`responsable` sans service

## 13) Limites actuelles et prochaines etapes

- Module RH: socle en place, workflows RH complets a etendre
- Evolutions metier courrier possibles selon vos regles operationnelles
- Extension decisionnelle et API metier a planifier
