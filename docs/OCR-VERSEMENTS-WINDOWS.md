# OCR des bordereaux de versement

## But

Le versement bancaire suit maintenant ce parcours :

1. téléversement du bordereau,
2. analyse OCR locale,
3. préremplissage du formulaire,
4. validation humaine,
5. enregistrement en base,
6. modifications ultérieures avec la même logique de verrouillage que les recettes.

## Dépendances recommandées sur Windows

### Tesseract OCR
Installez Tesseract OCR puis configurez son chemin dans `.env`.

Exemple :
`TESSERACT_BINARY=C:\Program Files\Tesseract-OCR\tesseract.exe`

### Conversion PDF
Le système essaie d'abord `pdftoppm`, puis bascule sur `magick` si nécessaire.

Exemples :
- `PDF_TO_IMAGE_BINARY=pdftoppm`
- `IMAGEMAGICK_BINARY=magick`

## Champs gérés

- gare affectée
- date opération
- date de la recette
- montant
- banque
- référence

## Remarques

- `date de la recette` reste volontairement manuelle.
- le document justificatif est conservé et reste lisible / téléchargeable.
- le texte OCR extrait est visible dans l'écran de validation pour contrôle utilisateur.
