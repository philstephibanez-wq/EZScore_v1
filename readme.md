# EZScore_v1 — R13.2 tri simplifié + pré-écoute audio

## Tri

Affichage simplifié :

```text
Trier par:  Titre  Interprète
```

Le choix actif est seulement souligné / coloré, sans bouton massif.

La barre alphabétique reste en dessous.

## Pré-écoute audio avant import

Après sélection d'un fichier audio dans la page Import, un lecteur HTML5 apparaît.

Formats concernés :

```text
MP3
WAV
FLAC
M4A
OGG
AAC
```

La pré-écoute utilise `URL.createObjectURL()` dans le navigateur :

- aucune analyse Python ;
- aucun envoi serveur supplémentaire ;
- le fichier n'est réellement importé que lorsque l'utilisateur clique sur le bouton d'import.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_SORT_AUDIO_PREVIEW_R13_2.zip" -C H:\EZScore_v1

php bin\console lint:yaml translations
php bin\console cache:clear
php bin\console lint:twig templates
```

Puis `Ctrl+F5`.

Aucune migration Doctrine.
