# EZScore_v1 — R10.6 bouton Import forcé visuellement

Cette correction ne dépend plus du chargement d'une nouvelle feuille CSS pour le bouton Import.

Le style critique du bouton est directement porté par le composant Twig afin d'éviter le rendu observé :

```text
Importer une chansonMP3 + fiche chanson
```

Affichage attendu :

```text
NOUVEL IMPORT
Importer une chanson
MP3 + fiche chanson
```

avec :
- gros bouton vert/bleu ;
- bordure claire ;
- icône `+` ;
- trois niveaux de texte séparés ;
- espacement visible.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_IMPORT_CTA_INLINE_R10_6.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
```

Puis :

```text
Ctrl+F5
```

## Vérification

```powershell
Select-String -Path .\templates\catalog\index.html.twig -Pattern "grid-template-columns:56px"
```

La commande doit retourner une ligne.

Aucune migration Doctrine.
Aucun changement sur l'import MP3.
Aucun changement de données.
