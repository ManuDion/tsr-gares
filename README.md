# TSR Gares Finance — Version V3.1

Application web Laravel 12 de gestion financière multi-gares pour **TSR Côte d’Ivoire**.

Ce `README.md` est le **document unique de référence** du projet.

---

## 1. Fonctionnalités livrées

### Modules financiers
- authentification sécurisée
- dashboard adapté au rôle, avec détail des types de recettes par gare et par période
- gestion des utilisateurs
- gestion des gares
- gestion des recettes avec 4 types de recette et montant total calculé
- gestion des dépenses avec saisie multiple jusqu’à 5 enregistrements en une seule validation
- gestion des versements bancaires
- justificatifs lisibles et téléchargeables
- historique détaillé des modifications
- historique système
- exports Excel
- base PWA

### OCR versements
- téléversement obligatoire du bordereau
- lecture automatique image / PDF selon la configuration serveur
- préremplissage des champs
- validation manuelle avant enregistrement
- support métier **Ecobank** et **Coris Bank**

### Ergonomie et responsive
- interface responsive sur mobile, tablette et desktop
- tableaux adaptatifs avec affichage empilé sur petit écran
- navigation mobile avec menu repliable
- formulaires et sélections adaptés aux écrans tactiles

### Nouveaux modules
- **Module Vérification** : contrôle par gare et par date de la règle  
  `Versements = Recettes - Dépenses`
- **Module Chat** : conversations privées ou multi-participants entre utilisateurs, sans module séparé de création de groupe
- **Module Documents administratifs** : suivi des permis, vignettes, visites techniques et autres documents réglementaires avec notifications automatiques d’échéance
- **Rôle Contrôleur** : accès dédié au module documents administratifs, avec accès également pour l’administrateur et le responsable

---

## 2. Rôles utilisateurs

### Administrateur
- accès global à toutes les gares
- accès complet aux modules financiers
- gestion des utilisateurs
- gestion des gares
- accès au dashboard global
- accès aux notifications
- accès à l’historique système
- accès au module Vérification
- accès au module Chat
- accès au module Documents administratifs

### Responsable
- interface de supervision équivalente à l’administrateur
- accès aux modules financiers
- accès aux notifications
- accès à l’historique système
- accès au module Vérification
- accès au module Chat
- accès au module Documents administratifs
- gestion des gares
- consultation / modification des utilisateurs existants

Restriction :
- ne peut pas créer un nouvel utilisateur

### Chef de gare
- accès à sa gare uniquement
- saisie des recettes
- saisie des dépenses
- saisie des versements
- réception de ses notifications métier
- accès au module Chat

### Caissière
- accès aux gares qui lui sont affectées
- saisie des recettes
- saisie des dépenses
- saisie des versements
- affectation multi-gares par cases à cocher
- accès au module Gare inchangé
- accès au module Chat

### Contrôleur
- accès au module Documents administratifs
- accès aux notifications liées aux documents administratifs
- accès au module Chat

---

## 3. Module Vérification

Le module **Vérification** permet de calculer, pour chaque gare et pour une date donnée :

- total recettes
- total dépenses
- total versements
- versement attendu = recettes - dépenses
- différence constatée

### Résultat
- si la différence est nulle : statut **Conforme**
- si la différence est différente de zéro : statut **Écart détecté**

### Actions superviseur
Pour un écart détecté, l’administrateur ou le responsable peut :
- **confirmer la différence**
- **activer les modifications**

### Effet de l’activation des modifications
L’activation ouvre une fenêtre d’ajustement sur les enregistrements de la gare et de la date concernées afin de permettre la régularisation par les acteurs terrain.

### Notifications
En cas d’écart :
- une notification est envoyée à l’**administrateur**
- une notification est envoyée au **responsable**
- le montant de la différence est indiqué

---

## 4. Module Chat

Le module **Chat** permet :
- les conversations **personne à personne**
- les conversations **multi-participants**
- l’envoi de messages internes entre utilisateurs

### Fonctionnement
- chaque utilisateur authentifié a accès au chat
- une conversation privée peut être créée entre deux utilisateurs
- l’utilisateur peut choisir un ou plusieurs participants lors du démarrage d’une conversation
- l’historique des messages reste consultable dans la conversation

---

## 5. Module Documents administratifs

Le module permet de gérer les documents réglementaires tels que :
- permis de conduire
- vignettes
- visites techniques
- autres documents administratifs

### Informations stockées
Pour chaque document :
- type de document
- intitulé / référence
- nom du fichier
- fichier PDF
- date d’expiration
- notes éventuelles

### Notifications automatiques
Quand un document approche de sa date d’expiration :
- à partir d’environ **30 jours avant l’échéance**, un rappel est généré **chaque semaine**
- durant la **dernière semaine**, un rappel est généré **chaque jour**
- après expiration, une notification d’expiration reste visible tant que le document n’a pas été mis à jour

### Destinataires
Les notifications sont envoyées aux profils :
- **Administrateur**
- **Responsable**
- **Contrôleur**

---

## 6. OCR des versements bancaires

### Parcours
1. téléversement du bordereau
2. lecture automatique
3. préremplissage
4. affichage des extraits OCR
5. validation / correction par l’utilisateur
6. enregistrement

### Champs de versement
- gare affectée
- date opération
- date de la recette
- montant
- banque
- référence = **nom de l’agence**
- description

### Configuration Windows OCR / PDF
Dans `.env` :

```env
OCR_DRIVER=local_tesseract
OCR_ENABLED=true
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"

PDF_TEXT_BINARY="C:/poppler/Library/bin/pdftotext.exe"
PDF_TO_IMAGE_BINARY="C:/poppler/Library/bin/pdftoppm.exe"
PDF_TO_IMAGE_CAIRO_BINARY="C:/poppler/Library/bin/pdftocairo.exe"

OCR_LANGUAGES=fra+eng
```

### Configuration production Linux / cPanel
Dans `.env`, utilisez les chemins Linux réels renvoyés par `which` :

```env
PDF_TEXT_BINARY="/usr/bin/pdftotext"
PDF_TO_IMAGE_BINARY="/usr/bin/pdftoppm"
PDF_TO_IMAGE_CAIRO_BINARY="/usr/bin/pdftocairo"
IMAGEMAGICK_BINARY="/usr/bin/magick"
GHOSTSCRIPT_BINARY="/usr/bin/gs"
```

---

## 7. Installation locale

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan optimize:clear
php artisan serve
```

### Comptes de démonstration
- `admin@tsr.test`
- `responsable@tsr.test`
- `chef.gare@tsr.test`
- `caissiere@tsr.test`
- `controleur@tsr.test`

Mot de passe :
```text
password
```

---

## 8. Déploiement production

### Préparation
- configurer `.env`
- mettre `APP_ENV=production`
- mettre `APP_DEBUG=false`
- configurer la base MySQL
- vérifier le stockage privé
- vérifier les chemins OCR / PDF si utilisés

### Commandes
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan optimize
```

### cPanel
Le sous-domaine doit pointer vers le dossier `public/` de Laravel, ou bien le contenu du dossier `public/` doit être exposé comme document root avec un `index.php` adapté.

---

## 9. Planificateur Laravel

Les tâches prévues :
- contrôle journalier financier
- vérification des écarts versement / recette / dépense
- contrôle des documents administratifs expirants

Configurer la crontab serveur pour exécuter le scheduler Laravel.

Exemple :
```bash
* * * * * php /chemin/vers/le-projet/artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Commandes utiles

```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

---

## 11. Notes importantes

- les notifications affichées sont ciblées selon le destinataire
- le rôle **Contrôleur** ne crée pas d’utilisateurs et ne saisit pas d’opérations financières
- les justificatifs et documents administratifs sont stockés en mode privé
- si l’OCR PDF ne fonctionne pas en production, vérifier **Poppler**, **Ghostscript**, **ImageMagick** et les permissions serveur

---

## 12. Version

Cette livraison correspond à la **Version N2** avec :
- module Vérification
- module Chat
- module Documents administratifs
- rôle Contrôleur
- README unique mis à jour


## Mise à jour Version N3

Cette version ajoute et ajuste :
- Chat : badge de messages non lus dans le menu, bouton **Nouvelle conversation**, cases à cocher pour les participants, purge automatique des conversations inactives après 2 semaines.
- Dépenses : modification autorisée sur le même principe que les recettes (48h ou déverrouillage superviseur), historique des modifications et page d’édition dédiée.
- Vérification : une notification par vérification et par superviseur, ouverture d’ajustement également sur les dépenses, tableau simplifié sans colonne statut, suppression des vérifications sur une période pour l’administrateur.
- Notifications : l’administrateur peut supprimer l’historique sur une période. Le contrôleur ne voit que les notifications documentaires.
- Documents administratifs : types par défaut **Permis de conduire**, **Vignettes**, **Visites techniques**, avec possibilité de saisir d’autres types. Seul le **contrôleur** peut créer ou mettre à jour les documents.
- Dashboard contrôleur : total documents, documents actifs, documents en zone critique, répartition par type et notifications documentaires récentes.

### Commandes après mise à jour
```bash
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan view:clear
php artisan serve
```


---

## Ajustements version N4

### Chat
- affichage par défaut de la liste des conversations
- bouton **Nouvelle conversation**
- bouton **Nouveau groupe** pour l’administrateur
- création de groupes avec affectation des utilisateurs par cases à cocher
- badge de messages non lus dans le menu
- suppression automatique des conversations inactives après 2 semaines

### Vérification
- tableau de consultation compacté pour rester entièrement visible
- actions de confirmation et d’ouverture des modifications conservées
- meilleure lisibilité des montants et de l’écart

### Dashboard
- les notifications récentes affichent uniquement celles du compte connecté
- déduplication des notifications d’écart sur le dashboard


---

## 10. Mise à jour Version N5

Cette version ajoute les ajustements suivants :

- suppression du bouton dédié **Nouveau groupe**
- une **Nouvelle conversation** permet désormais de sélectionner un ou plusieurs participants
- amélioration de l’affichage des noms de conversations multi-participants
- responsive renforcé sur l’ensemble de l’application
- tableaux adaptatifs sur mobile avec lecture ligne par ligne
- tableau du module Vérification compacté
- déduplication renforcée des notifications d’écart sur le dashboard

Aucune migration supplémentaire n’est nécessaire pour cette version.

Commandes conseillées après mise à jour :

```bash
composer dump-autoload
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

## Ajustement UI N6

- Dans les tableaux financiers, l'unité FCFA est désormais portée par les en-têtes de colonnes.
- Dans le module Vérification, les actions utilisent des icônes compactes pour garder la colonne Écart sur une seule ligne.


---

## 11. Évolutions Version V3

### Recettes
Les recettes sont désormais ventilées en **4 types obligatoires** :
- ventes de tickets inter
- ventes de tickets national
- transport de bagages inter
- transport de bagages national

Le champ **Référence** n’est plus utilisé dans la saisie des recettes.  
Le champ **Montant** est maintenant **calculé automatiquement** et enregistré comme la somme des 4 montants saisis.

### Dépenses
La création de dépense permet maintenant une **saisie multiple** :
- ajout d’un nouveau formulaire avec le bouton **Ajouter une dépense**
- maximum **5 enregistrements** sur le même écran
- au clic sur **Enregistrer les dépenses**, toutes les saisies valides sont sauvegardées immédiatement en base de données

### Migration nécessaire
Après mise à jour vers cette version :

```bash
composer dump-autoload
php artisan migrate
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

### Compatibilité des anciennes recettes
Lors de la migration, les anciennes recettes existantes sont conservées.  
Leur montant historique est automatiquement reporté dans :
- **Ventes tickets inter**
et les 3 autres montants sont initialisés à `0`.

