# Progiciel TSR - README complet et a jour

Application Laravel 12 pour la gestion TSR, organisee par modules metiers.

## 1) Vue d'ensemble

Le progiciel est structure autour de 4 modules:

- Gares
- Documents
- Courrier
- RH

Les roles `admin` et `responsable` sont universels (vision globale).
Des roles d'administrateur par service existent aussi avec un perimetre limite a leur service.
Les autres roles sont limites a leur module metier.

## 2) Fonctionnalites principales

### 2.1 Module Gares

- Saisie des recettes
- Saisie des depenses (multi-lignes)
- Saisie des versements avec justificatif
- Parametrage de routage bancaire par periode (global ou cible par gare)
- Ajout de plusieurs justificatifs (jusqu'a 10 photos/fichiers) pour chaque recette et chaque depense
- Controle des ecarts / verifications
- Etat des ecritures manquantes par date/gare avec export PDF
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

### 3.1.b Administrateurs par service

- Administrateur Gares (`admin_gares`)
- Administrateur Courrier (`admin_courrier`)
- Administrateur RH (`admin_rh`)
- Administrateur Documents (`admin_documents`)

Regle:

- Un administrateur de service dispose des memes prerogatives qu'un administrateur, mais uniquement dans son service.
- Il ne peut pas creer, modifier ou supprimer des utilisateurs hors de son service.
- Il ne peut pas promouvoir un compte en role universel (`admin` / `responsable`).

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
- Les roles `admin_*` sont obligatoirement lies a leur service.

### 3.3 Creation utilisateur (formulaire)

Le formulaire supporte:

- Choix du module principal (ou aucun pour les superviseurs universels)
- Filtrage automatique des roles selon le module
- Affectation gare principale pour certains roles
- Affectation multi-gares (dont verificateur)
- Option "toutes les gares"
- Parametrage caissier du type de recettes a recuperer:
  - `national_only`
  - `inter_only`
  - `both`
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
- Envoi de messages audio (type WhatsApp/Skype):
  - enregistrement micro depuis l'ecran de conversation
  - bouton micro unique (1 clic demarre, 1 clic arrete)
  - option de selection manuelle de fichier audio retiree de l'interface
  - lecture audio integree dans le fil de discussion

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

### 5.5 Deverrouillage de modification parametre

- L'ancien deverrouillage fixe `24h` est remplace par un deverrouillage a duree choisie par l'initiateur.
- Cette regle s'applique aux:
  - recettes
  - depenses
  - versements
  - autorisations d'ajustement depuis le module Verification
- L'initiateur renseigne:
  - une duree numerique
  - une unite (`minutes`, `heures`, `jours`)
- Le systeme calcule automatiquement la date/heure de fin du deverrouillage.

### 5.6 Lecture interne des fichiers et telechargement

- Les justificatifs et documents (PDF/image) sont consultes via un lecteur interne integre a l'application.
- Le bouton `Lire` ouvre une fenetre de lecture interne (sans ouverture d'onglet externe).
- Le telechargement est strictement limite aux roles universels:
  - `admin`
  - `responsable`
- Les administrateurs de service (`admin_gares`, `admin_courrier`, `admin_rh`, `admin_documents`) ne peuvent pas telecharger les fichiers.
- La restriction est appliquee:
  - dans l'interface (bouton telechargement masque)
  - au niveau serveur (controle d'autorisation)

### 5.7 Regle montants FCFA (entiers)

- Tous les montants financiers sont traites en entiers FCFA (pas de decimales).
- En saisie, les caracteres non numeriques sont nettoyes.
- En affichage, les montants restent sans virgule decimale.
- En exports Excel/PDF, les montants sont harmonises en entier.

### 5.8 Verifications avancees (qualite des donnees)

- Contrainte d'unicite sur les recettes: `service_scope + gare_id + operation_date`
- Contrainte d'unicite sur les versements: `service_scope + gare_id + operation_date + account_type`
- Ecran `Ecritures manquantes` pour identifier les jours incomplets
- Export PDF des ecritures manquantes pour partage terrain/controle
- Les gares desactivees sont exclues du module Verification et de la fiche des ecritures manquantes

## 6) Notifications et historique systeme

- Les notifications sont filtrees par module actif
- L'historique systeme est filtre par module actif
- En pratique, un utilisateur dans un module ne voit que les elements pertinents de ce module
- Les notifications liees a une gare desactivee ne sont plus affichees dans les modules financiers

## 6.1 Ergonomie mobile et responsive

- Optimisation des tableaux pour tablettes et telephones:
  - Gestion des utilisateurs
  - Gestion des recettes
  - Gestion des versements
  - Historique des notifications
  - Module Verification
- Ajustement des largeurs de colonnes et retours a la ligne pour eviter les textes verticaux
- Filtre rapide `Aujourd'hui` ajoute dans l'historique des notifications

## 6.2 Tableau de bord financier

- Ajout de la carte `Gares actives` avant `Total recettes`
- Cartes `Gares actives`, `Total recettes`, `Total depenses`, `Total versements` compactees et alignees sur une meme ligne (ecran large)

## 7) OCR versements

Le pre-remplissage OCR des versements est retire dans cette version de production.
Parcours actuel:

1. Upload PDF / image justificative
2. Saisie manuelle
3. Enregistrement

## 8) Base de donnees - points importants

- `service_scope` sur flux financiers et verifications
- `users.allow_multi_gare_entry` pour autoriser une saisie multi-gares
- `users.cashier_collection_mode` pour limiter la collecte caissier (`both`, `inter_only`, `national_only`)
- `bank_routing_overrides` pour forcer le compte bancaire (`national` / `inter`) par periode
- `bank_routing_override_gare` pour cibler des gares precises sur un override bancaire
- Tables socle RH: `employees`, `employee_assignments`, `employee_documents`, etc.
- Chat:
  - `conversation_type` (`general`, `service_internal`, `direct`)
  - `service_module` sur conversations de service
  - `chat_messages.message_type` (`text` ou `audio`)
  - metadonnees audio sur les messages: `audio_disk`, `audio_path`, `audio_mime_type`, `audio_size`

Migrations recentes a verifier:

- `2026_05_10_090000_refonte_services_and_rh_foundation.php`
- `2026_05_11_120000_add_conversation_type_to_conversations_table.php`
- `2026_05_11_130000_add_service_module_to_conversations_table.php`
- `2026_05_12_150000_add_unique_recette_per_day_per_gare_scope.php`
- `2026_05_14_090000_add_allow_multi_gare_entry_to_users.php`
- `2026_05_14_100000_add_unique_versement_per_day_per_bank_and_scope.php`
- `2026_05_15_110000_create_bank_routing_override_gare_table.php`
- `2026_05_16_090000_add_cashier_collection_mode_to_users.php`
- `2026_05_16_120000_add_audio_support_to_chat_messages.php`

## 9) Installation locale

### Prerequis

- PHP 8.2+
- Composer
- MySQL ou MariaDB
- Extensions PHP requises par Laravel
- Pour l'export PDF de la fiche des ecritures manquantes: paquet `dompdf/dompdf` installe via Composer

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

## 11.1 Mise a niveau sans perte de donnees (MySQL existant)

Si votre base contient deja des donnees en production, utilisez une mise a niveau progressive:

1. Sauvegarde complete (dump SQL)
2. Test de la mise a niveau en preproduction
3. Execution du script SQL idempotent de mise a niveau
4. Verification applicative
5. Deploiement final

Scripts SQL fournis dans le projet:

- `docs/sql/mysql_upgrade_safe_2026_05_09.sql`
- `docs/sql/mysql_upgrade_safe_2026_05_15.sql` (incremental)

Ordre recommande:

1. `mysql_upgrade_safe_2026_05_09.sql`
2. `mysql_upgrade_safe_2026_05_15.sql`

Exemple d'execution:

```bash
mysql -u VOTRE_USER -p VOTRE_BASE < docs/sql/mysql_upgrade_safe_2026_05_09.sql
mysql -u VOTRE_USER -p VOTRE_BASE < docs/sql/mysql_upgrade_safe_2026_05_15.sql
```

Puis:

```bash
php artisan optimize:clear
php artisan view:cache
```

Notes importantes:

- Le script est idempotent (relancable sans destruction).
- Les donnees existantes sont conservees.
- Si des doublons existent deja dans `recettes` (meme `service_scope + gare_id + operation_date`), la contrainte unique n'est pas appliquee automatiquement et un message d'alerte est renvoye.
- Si des doublons existent deja dans `versement_bancaires` (meme `service_scope + gare_id + operation_date + account_type`), la contrainte unique n'est pas appliquee automatiquement et un message d'alerte est renvoye.
- Le controle metier "gare inter_only incompatible avec service courrier" est applique par l'application (validation metier), pas par contrainte SQL native.
- En cas d'erreur MySQL `#1267 Illegal mix of collations`, assurez-vous d'utiliser les scripts SQL a jour (version courante du depot), puis relancez apres nettoyage des procedures temporaires:

```sql
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;
DROP PROCEDURE IF EXISTS sp_add_fk_if_missing;
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;
```

## 11.2 Procedure cPanel (sans ecraser la base de donnees)

Objectif: mettre a jour le code applicatif en production sans suppression des donnees metier.

### A. Checklist avant mise a jour

1. Verifier que le `.env` production est correct (`APP_ENV=production`, `APP_DEBUG=false`).
2. Confirmer que vous avez acces a:
   - cPanel File Manager (ou Git Version Control)
   - Terminal cPanel (ou SSH)
   - phpMyAdmin
3. Avoir une sauvegarde locale de la nouvelle version du projet.

### B. Sauvegardes obligatoires

1. Mettre le site en maintenance:

```bash
cd ~/public_html
php artisan down
```

2. Sauvegarder la base MySQL:

```bash
mysqldump -u VOTRE_USER -p VOTRE_BASE > ~/backup_tsr_$(date +%F_%H%M).sql
```

3. Sauvegarder `.env`:

```bash
cp .env ~/env_backup_tsr_$(date +%F_%H%M)
```

4. Option recommande: sauvegarder aussi `storage/` (logs + fichiers locaux).

### C. Mise a jour du code (sans toucher a la DB)

Option 1 (recommandee): `git pull` sur le serveur si votre depot est connecte.

Option 2: upload ZIP via cPanel, puis extraction en remplacant le code applicatif.

Regles de securite:

- Ne pas supprimer `.env`.
- Ne pas ecraser `storage/` si vous y conservez des fichiers utilisateurs.
- Ne pas importer de dump SQL vide/ancien par erreur.

### D. Commandes de mise a jour Laravel

Depuis la racine du projet en production:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

Si vous utilisez les queues:

```bash
php artisan queue:restart
```

### E. Scripts SQL de mise a niveau (si necessaire)

Executer uniquement les scripts incrementaux necessaires:

```bash
mysql -u VOTRE_USER -p VOTRE_BASE < docs/sql/mysql_upgrade_safe_2026_05_09.sql
mysql -u VOTRE_USER -p VOTRE_BASE < docs/sql/mysql_upgrade_safe_2026_05_15.sql
```

Puis relancer `php artisan migrate --force`.

### F. Remise en ligne + verification

1. Sortir du mode maintenance:

```bash
php artisan up
```

2. Verifier dans l'application:
   - Connexion / deconnexion
   - Recettes / Depenses / Versements
   - Notifications / Historique
   - Lecture et telechargement des justificatifs

### G. Commandes interdites en production (risque de perte de donnees)

Ne jamais lancer sur la production:

```bash
php artisan migrate:fresh
php artisan db:wipe
php artisan db:seed --force
```

Sauf cas exceptionnel explicitement valide, ne pas executer de `DROP TABLE`, `TRUNCATE` ou import SQL destructif.

### H. Rollback rapide (si anomalie apres mise a jour)

1. `php artisan down`
2. Restaurer le dump SQL sauvegarde.
3. Restaurer `.env` sauvegarde.
4. Revenir au code precedent.
5. `php artisan optimize:clear`
6. `php artisan up`

## 12) Changelog recent (mise a niveau)

- Chat aligne sur 3 modalites: `general`, `service_internal`, `direct`
- Ecran dedie `Nouvelle conversation`
- Selection interlocuteur direct via filtre/auto-completion
- Messages audio dans le chat:
  - enregistrement micro integre
  - bouton micro en mode toggle (demarrer/arreter)
  - lecture audio dans la conversation
- Parametrage bancaire:
  - override du compte de versement (`national` / `inter`) par plage de dates
  - ciblage optionnel d'un ensemble de gares pour un override
- Verification:
  - nouvel ecran `Ecritures manquantes`
  - export PDF des ecritures manquantes
- Integrite des donnees:
  - unicite `recettes` par `service_scope + gare_id + operation_date`
  - unicite `versement_bancaires` par `service_scope + gare_id + operation_date + account_type`
- Notifications et historique systeme filtres par module
- Dashboard Gares/Courrier: courbe evolutive jour par jour (1 a 31)
- Historique detaille: colonne `Objet` retiree, colonne `Action` fusionnee (voir + supprimer)
- Historique detaille: affichage de tableau ajuste pour une meilleure lecture sur petits ecrans
- Comparatif hebdomadaire conserve (S1 a S4)
- Correction duplication des blocs globaux lors du filtre sur une gare
- `Detail des types de recettes par gare` en Top 5
- Recettes Courrier en type unique
- Formulaire utilisateur:
  - verificateur avec choix des gares
  - affichage `Superviseur universel` / `Superviseur limite a X gare(s)`
  - possibilite de creer `admin`/`responsable` sans service
  - ajout des roles `admin_gares`, `admin_courrier`, `admin_rh`, `admin_documents`
  - les administrateurs de service sont bloques sur leur propre perimetre
  - les caissiers peuvent etre configures pour collecter uniquement les recettes nationales, uniquement internationales, ou les deux
- Deverrouillage de modification:
  - remplacement du mode fixe `24h`
  - duree desormais definie a l'initiation (`minutes`, `heures` ou `jours`)
  - meme logique appliquee aux autorisations d'ajustement dans Verification
- Fichiers justificatifs et documents:
  - lecture via lecteur interne integre a l'application
  - telechargement reserve a `admin` et `responsable` uniquement
  - administrateurs de service exclus du telechargement
- Recettes et depenses:
  - jusqu'a 10 photos/fichiers justificatifs par enregistrement
- Harmonisation montants:
  - montants financiers en entiers FCFA sur saisie, controle et export Excel/PDF
- Ergonomie mobile:
  - optimisation des tableaux Recettes, Versements, Verification, Utilisateurs et Notifications
  - ajustements des colonnes et tailles de badges/boutons pour une meilleure lisibilite sur petit ecran
- Regles gares actives:
  - exclusion des gares desactivees dans Verification et Ecritures manquantes
  - notifications financieres filtrees pour ne plus remonter les gares desactivees
- Tableau de bord:
  - ajout du KPI `Gares actives`
  - compactage des 4 cartes financieres principales sur une seule ligne en ecran large
- Encodage FR:
  - correction des libelles avec accents sur les ecrans financiers et notifications

## 13) Limites actuelles et prochaines etapes

- Module RH: socle en place, workflows RH complets a etendre
- Evolutions metier courrier possibles selon vos regles operationnelles
- Extension decisionnelle et API metier a planifier
