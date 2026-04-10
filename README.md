# TSR Gares Finance

Application Laravel 12 de gestion financière multi-gares pour **TSR Côte d'Ivoire**.

Ce README est désormais le **document de référence unique** pour l'installation, la configuration, les rôles, l'OCR des bordereaux, les notifications, les exports et le dépannage courant. Vous n'avez plus besoin de lire plusieurs fichiers séparés pour exploiter le projet.

---

## 1. Fonctionnalités livrées

### Authentification et rôles
Profils pris en charge :
- **Administrateur**
- **Responsable**
- **Chef de gare**
- **Caissière**

### Gestion métier
- gestion des gares
- gestion des utilisateurs
- gestion des recettes
- gestion des dépenses
- gestion des versements bancaires
- historique des modifications
- historique des notifications
- exports Excel
- dashboard dynamique et adapté au rôle
- stockage privé des justificatifs
- base PWA

### Notifications et contrôle journalier
- contrôle journalier automatique à **10h00 GMT / Afrique-Abidjan**
- suivi des gares conformes / en anomalie
- alertes visibles sur dashboard
- historique des notifications

### Versements bancaires OCR
Le module versement fonctionne désormais selon ce cheminement :
1. téléversement du bordereau
2. lecture automatique du document
3. préremplissage des champs
4. vérification / correction utilisateur
5. validation
6. enregistrement en base
7. consultation / lecture / téléchargement du bordereau

Le bordereau est **obligatoire**, y compris en saisie manuelle.

---

## 2. Rôles utilisateurs

### Administrateur
Droits :
- consulter toutes les gares
- consulter toutes les recettes, dépenses et versements
- gérer les utilisateurs
- gérer les gares
- consulter le dashboard global
- consulter les notifications
- consulter l'historique des notifications
- exporter les données

Restriction :
- pas un profil de saisie terrain

### Responsable
Droits :
- consulter toutes les gares
- consulter recettes, dépenses, versements
- consulter le dashboard
- consulter les notifications
- consulter les rapports financiers
- consulter l'historique des notifications
- exporter

Restrictions :
- ne crée pas de recette
- ne crée pas de dépense
- ne crée pas de versement

### Chef de gare
Droits :
- consulter uniquement sa gare
- enregistrer recettes, dépenses et versements de sa gare
- modifier les recettes et versements selon les règles métier
- consulter ses notifications métier

Particularités :
- gare imposée par la connexion
- aucun choix de gare à la saisie
- alertes visibles sur le dashboard

### Caissière
Droits :
- consulter les données des gares affectées
- enregistrer recettes, dépenses et versements

Particularités :
- choix de la gare au moment de la saisie
- seules les gares autorisées sont proposées
- possibilité d'affecter plusieurs gares ou toutes les gares au moment de la création utilisateur

---

## 3. Pré-requis techniques

- PHP **8.2+**
- MySQL **8+**
- Composer
- extensions PHP Laravel usuelles :
  - pdo_mysql
  - mbstring
  - openssl
  - fileinfo
  - tokenizer
  - xml
  - ctype
  - json

### Recommandé pour l'OCR PDF
- **Tesseract OCR**
- **Poppler** (`pdftotext`, `pdftoppm`, `pdftocairo`)  
  ou à défaut
- **ImageMagick**
- **Ghostscript**
- **MuPDF / mutool**
- extension PHP **Imagick** si disponible

Le projet essaie plusieurs méthodes automatiquement pour les PDF :
1. extraction texte PDF directe
2. conversion PDF → image
3. OCR Tesseract sur l'image générée

---

## 4. Installation

### 4.1 Copier le projet et installer les dépendances

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 4.2 Configurer MySQL

Dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tsr_gares_finance
DB_USERNAME=root
DB_PASSWORD=
```

### 4.3 Lancer la base et le stockage

```bash
php artisan migrate --seed
php artisan storage:link
php artisan optimize:clear
php artisan serve
```

Application :
- `http://127.0.0.1:8000`
- connexion : `/login`

---

## 5. Comptes de démonstration

Après `php artisan migrate --seed` :

- **admin@tsr.test** / `password`
- **responsable@tsr.test** / `password`
- **chef.gare@tsr.test** / `password`
- **caissiere@tsr.test** / `password`

---

## 6. Configuration OCR Windows

Exemple recommandé dans `.env` :

```env
OCR_DRIVER=local_tesseract
OCR_ENABLED=true
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
PDF_TEXT_BINARY=pdftotext
PDF_TO_IMAGE_BINARY=pdftoppm
PDF_TO_IMAGE_CAIRO_BINARY=pdftocairo
MUTOOL_BINARY=mutool
IMAGEMAGICK_BINARY=magick
GHOSTSCRIPT_BINARY=gswin64c
OCR_LANGUAGES=fra+eng
OCR_BANK_KEYWORDS="ECOBANK,CORIS BANK,CORIS,NSIA BANQUE,NSIA,SGBCI,SGCI,BNI,UBA,BOA,BANK OF AFRICA,SIB,ORABANK,BICICI,ACCESS BANK,BDK"
JUSTIFICATIF_MAX_SIZE_KB=5120
JUSTIFICATIF_PRIVATE_DISK=private
```

### Important sur Windows
- utilisez **des slashs `/`** dans les chemins du `.env`
- mettez les chemins avec espaces **entre guillemets**
- exemple correct :

```env
TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
```

### Si `php artisan optimize:clear` échoue à cause du `.env`
Vérifiez qu'il n'y a :
- ni antislash non échappé `\`
- ni espace non protégé
- ni guillemet typographique

---

## 7. OCR des bordereaux de versement

### Documents pris en compte
La version actuelle est optimisée pour les deux modèles transmis :
- **Ecobank**
- **Coris Bank**

### Champs préremplis automatiquement
- **Gare affectée**  
  suggérée via le document ou imposée par la connexion pour un chef de gare
- **Date opération**
- **Montant**
- **Banque**
- **Référence**

### Champ restant manuel
- **Date de la recette**

### Règles métier
- le bordereau est obligatoire, même en saisie manuelle
- si l'OCR aboutit, les champs sont préremplis
- si l'OCR échoue, le bordereau est conservé et l'utilisateur peut poursuivre en manuel
- la validation finale reste humaine
- le bordereau reste lisible et téléchargeable après enregistrement
- la modification d'un versement suit la même logique métier que les recettes

### Mapping Ecobank
Préremplissage privilégié depuis :
- `DATE`
- `REFERENCE`
- `MONTANT VERSE`
- `MONTANT CREDITE`
- `AGENCE`, `MOTIF`, `REMARQUES` pour suggérer une gare

### Mapping Coris Bank
Préremplissage privilégié depuis :
- date/heure d'opération
- `BORDEREAU DE VERSEMENT ESPECES N° ...`
- `MONTANT NET`
- `MONTANT`
- `ADRESSE`, `MOTIF`, `AGENCE` pour suggérer une gare

---

## 8. Notifications et planification

### Commande manuelle
```bash
php artisan gares:run-daily-control
```

### Planification serveur
La tâche est planifiée pour **10h00 Afrique/Abidjan**.

Exemple cron Linux :

```bash
* * * * * cd /chemin/vers/tsr-gares && php artisan schedule:run >> /dev/null 2>&1
```

Sur Windows, utilisez le **Planificateur de tâches** pour exécuter périodiquement :

```bash
php artisan schedule:run
```

---

## 9. Exports

Exports disponibles :
- recettes
- dépenses
- contrôles journaliers

Format :
- Excel
- une feuille par date sur la période sélectionnée

---

## 10. Justificatifs

Les justificatifs de dépenses et de versements sont stockés sur un disque privé.

Fonctions disponibles :
- lecture
- téléchargement
- consultation depuis la fiche concernée

---

## 11. Utilisation rapide du module versement

### Cas normal
1. ouvrir **Versements**
2. cliquer sur **Nouveau versement**
3. téléverser le bordereau
4. vérifier les champs préremplis
5. renseigner **Date de la recette**
6. valider

### Si l'OCR ne lit pas le document
1. téléverser quand même le bordereau
2. laisser l'application conserver le document
3. compléter manuellement les champs
4. valider

---

## 12. Dépannage courant

### L'image passe mais pas le PDF
Causes fréquentes :
- `pdftotext` absent
- `pdftoppm` absent
- `pdftocairo` absent
- ImageMagick installé sans support PDF
- Ghostscript absent

Solutions :
- installer **Poppler for Windows**
- ou installer **ImageMagick + Ghostscript**
- ou installer **MuPDF / mutool**
- puis relancer :

```bash
php artisan optimize:clear
php artisan serve
```

### L'OCR ne remplit pas bien un champ
Vérifiez :
- la lisibilité du scan
- la présence du mot-clé sur le bordereau
- la qualité de la date
- l'exactitude des noms de gares dans la base

### L'application ne démarre pas après modification du `.env`
Lancez :

```bash
php artisan optimize:clear
```

Puis corrigez les lignes OCR mal formatées.

---

## 13. Commandes utiles

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
php artisan gares:run-daily-control
```

---

## 14. Notes de version intégrées

Cette version intègre notamment :
- OCR réel sur images
- amélioration OCR PDF avec plusieurs stratégies
- mapping spécifique **Ecobank / Coris Bank**
- bordereau obligatoire même en manuel
- fallback manuel si lecture automatique échoue
- README unifié
- lecture et téléchargement des justificatifs
- rôles et menus adaptés
- dashboard dynamique et responsive
