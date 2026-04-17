# TSR Gares Finance — Version N

Application web Laravel 12 de gestion financière multi-gares pour **TSR Côte d’Ivoire**.

Ce `README.md` est le **document unique de référence** du projet. Les anciens fichiers `FIX-*.md` ont été retirés de la livraison finale afin d’éviter plusieurs documents à consulter.

---

## 1. Fonctionnalités livrées

### Modules principaux
- authentification sécurisée
- tableau de bord dynamique
- gestion des utilisateurs
- gestion des gares
- gestion des recettes
- gestion des dépenses
- gestion des versements bancaires
- justificatifs lisibles et téléchargeables
- notifications ciblées selon le rôle et les gares autorisées
- historique des modifications
- historique système détaillé
- exports Excel
- base PWA
- interface responsive mobile / tablette / desktop

### Ergonomie
- menu adaptatif selon le rôle
- interface harmonisée avec les couleurs et le style de la page de connexion
- titres et boutons modernisés
- marges et espacements uniformisés dans toute l’application
- formulaires et tableaux utilisables sur mobile

---

## 2. Rôles utilisateurs

### Administrateur
Droits :
- consulter toutes les gares
- consulter toutes les recettes, dépenses et versements bancaires
- gérer les utilisateurs
- gérer les gares
- consulter le dashboard global
- consulter les notifications
- consulter l’historique des notifications
- consulter l’historique système
- exporter les données

Restriction :
- n’est pas un profil de saisie terrain

### Responsable
Droits :
- consulter toutes les gares
- consulter les recettes, dépenses et versements bancaires
- consulter le dashboard financier
- consulter les notifications
- consulter les rapports financiers
- consulter l’historique des notifications
- consulter l’historique système
- exporter les données

Restrictions :
- ne peut pas créer de recette
- ne peut pas créer de dépense
- ne peut pas créer de versement bancaire

### Chef de gare
Droits :
- consulter les données liées à sa gare
- enregistrer les recettes de sa gare
- enregistrer les dépenses de sa gare
- enregistrer les versements bancaires de sa gare
- modifier certaines recettes et certains versements selon la règle métier
- consulter ses notifications métier

Particularités :
- la gare est imposée automatiquement
- aucun choix de gare n’est affiché à la saisie
- le menu **Gares** n’apparaît pas
- des alertes sont visibles sur le dashboard en cas d’oubli de saisie

### Caissière
Droits :
- consulter les données des gares qui lui sont attribuées
- enregistrer les recettes
- enregistrer les dépenses
- enregistrer les versements bancaires

Particularités :
- la gare est choisie au moment de la saisie
- seules les gares autorisées sont proposées
- l’affectation des gares se fait par **cases à cocher**
- l’option **Toutes les gares actives** est disponible

---

## 3. Parcours métier des versements bancaires

Le versement suit désormais ce flux :

1. téléversement obligatoire du bordereau
2. lecture automatique du document
3. préremplissage des champs
4. affichage des extraits OCR à côté des champs
5. vérification / correction par l’utilisateur
6. validation
7. enregistrement en base
8. lecture / téléchargement du bordereau

### Champs de versement
- **Gare affectée** : issue de la connexion pour le chef de gare, choisie parmi les gares autorisées pour la caissière
- **Date opération** : préremplie
- **Date de la recette** : saisie manuelle
- **Montant** : prérempli
- **Banque** : préremplie
- **Référence** : correspond au **nom de l’agence**
- **Description** : libre si besoin

### OCR pris en charge
- **Ecobank** : modèle international
- **Coris Bank** : modèle national

### Règles OCR
- si l’analyse aboutit, les champs sont préremplis
- si l’analyse échoue, le bordereau reste conservé et l’utilisateur peut continuer en saisie manuelle
- les PDF nécessitent la présence de Poppler sur Windows

---

## 4. Justificatifs

### Recettes
- ajout possible d’un justificatif
- lecture dans l’interface
- téléchargement

### Dépenses
- justificatif pris en charge
- lecture dans l’interface
- téléchargement

### Versements
- bordereau obligatoire
- lecture dans l’interface
- téléchargement

Tous les justificatifs sont stockés sur un disque privé.

---

## 5. Historique et audit

### Historique détaillé des modifications
Les modifications importantes sont historisées avec :
- l’utilisateur ayant fait la modification
- la date et l’heure
- l’objet concerné
- la gare associée
- la description
- le détail des **valeurs avant modification** et **après modification**

### Règle appliquée
Une saisie sans changement réel ne doit pas créer d’historique de modification.

### Historique système
Accessible uniquement à :
- l’administrateur
- le responsable

Tableau disponible avec les colonnes :
- **Objet**
- **Utilisateur**
- **Date et Heure**
- **Événement**
- **Gare**
- **Description**
- **Détail**

---

## 6. Notifications

### Contrôle journalier
Le système exécute un contrôle journalier à **10h00 GMT** sur les données du jour précédent.

### Règles de diffusion
- **Admin** et **Responsable** reçoivent uniquement les notifications de supervision globale
- **Chef de gare** reçoit uniquement les notifications de sa gare
- **Caissière** reçoit uniquement les notifications des gares qui lui sont affectées

### Affichage
Le tableau des notifications affiche :
- **Date génération**
- **Objet**
- **Gares**
- **Opérations**
- **Statut**

Le statut `generated` est affiché en français : **Générée**.

---

## 7. Dashboard et rapports

### Dashboard
Le dashboard est adapté au rôle utilisateur :
- le chef de gare voit uniquement les indicateurs utiles à sa gare
- les superviseurs voient une vue globale consolidée
- les alertes de non-saisie sont visibles dès la connexion

### Rapports de supervision
Page **Top 5 et rapports de supervision** :
- Top 5 en saisie
- Top 5 recettes
- Top 5 dépenses

Les trois blocs sont affichés sur la même ligne sur grand écran.

---

## 8. Pré-requis techniques

- PHP **8.2+**
- MySQL **8+**
- Composer
- Node.js facultatif si vous souhaitez enrichir le front plus tard
- extensions PHP usuelles Laravel :
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `fileinfo`
  - `tokenizer`
  - `xml`
  - `ctype`
  - `json`

### OCR recommandé
- **Tesseract OCR**
- **Poppler for Windows**
  - `pdftotext.exe`
  - `pdftoppm.exe`
  - `pdftocairo.exe`

---

## 9. Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve
```

---

## 10. Configuration MySQL

Dans le fichier `.env` :

```env
APP_NAME="TSR Gares Finance"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tsr_gares
DB_USERNAME=root
DB_PASSWORD=
```

---

## 11. Configuration OCR Windows

### Tesseract
Exemple :

```env
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
```

### Poppler
Exemple :

```env
PDF_TEXT_BINARY="C:/poppler/Library/bin/pdftotext.exe"
PDF_TO_IMAGE_BINARY="C:/poppler/Library/bin/pdftoppm.exe"
PDF_TO_IMAGE_CAIRO_BINARY="C:/poppler/Library/bin/pdftocairo.exe"
```

### Bloc conseillé

```env
OCR_DRIVER=local_tesseract
OCR_ENABLED=true
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
PDF_TEXT_BINARY="C:/poppler/Library/bin/pdftotext.exe"
PDF_TO_IMAGE_BINARY="C:/poppler/Library/bin/pdftoppm.exe"
PDF_TO_IMAGE_CAIRO_BINARY="C:/poppler/Library/bin/pdftocairo.exe"
OCR_LANGUAGES=fra+eng
OCR_BANK_KEYWORDS="ECOBANK,CORIS BANK,CORIS"
```

---

## 12. Comptes de démonstration

Après `php artisan migrate --seed` :

- `admin@tsr.test`
- `responsable@tsr.test`
- `chef.gare@tsr.test`
- `caissiere@tsr.test`

Mot de passe :

```text
password
```

---

## 13. Commandes utiles

### Lancer l’application
```bash
php artisan serve
```

### Nettoyer les caches
```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

### Réinitialiser la base
```bash
php artisan migrate:fresh --seed
```

### Lancer le scheduler en local
```bash
php artisan schedule:work
```

---

## 14. Dépannage rapide

### Erreur `.env` sur les chemins Windows
Utilisez des **slashes `/`** et gardez les chemins entre guillemets :

```env
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
```

### OCR PDF ne fonctionne pas
Vérifiez :
- que Poppler est bien installé
- que `pdftotext.exe`, `pdftoppm.exe` et `pdftocairo.exe` existent
- que les chemins sont corrects dans `.env`

### L’image OCR fonctionne mais pas le PDF
Dans la plupart des cas, il manque Poppler ou les chemins `.env` sont erronés.

### Un changement ne doit pas créer d’historique
La version finale ignore désormais les mises à jour sans différence réelle entre les valeurs avant/après.

---

## 15. Arborescence utile

- `app/Http/Controllers` : contrôleurs métier
- `app/Services/DocumentAnalysisService.php` : OCR / lecture documentaire
- `app/Services/DailyControlService.php` : contrôle journalier / notifications
- `app/Services/ActivityLogService.php` : historique système
- `resources/views` : vues Blade
- `public/assets/app.css` : thème principal
- `storage/app/private` : stockage privé des justificatifs

---

## 16. Évolutions possibles pour une version encore plus professionnelle

- workflow de validation à 2 niveaux avec approbation superviseur
- notifications email / SMS / WhatsApp
- recherche globale plein texte sur opérations et justificatifs
- export PDF des journaux d’audit
- OCR enrichi avec modèles bancaires supplémentaires
- file d’attente Laravel pour l’OCR et les exports lourds
- tableau de bord décisionnel par gare, zone, période et opérateur
- mode hors ligne PWA avec synchronisation différée
- journal de connexion et suivi de sécurité renforcé

---

## 17. Livraison finale

Cette version est livrée comme **version finale consolidée** avec :
- une interface harmonisée
- un OCR versements opérationnel
- un historique système exploitable
- des notifications filtrées par destinataire
- un README unique

