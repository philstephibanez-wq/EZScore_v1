# EZScore_v1 — R10.3 Import MP3 fiable + signature Auto

Cette livraison corrige les deux problèmes constatés sur la page d'import.

## 1. Signature rythmique

La valeur par défaut à l'import est maintenant :

```text
Auto
```

Valeurs disponibles :

```text
Auto
2/4
3/4
4/4
6/8
```

La valeur persistée pour Auto est :

```text
auto
```

Aucune analyse n'est encore lancée : ce champ indique simplement que la future chaîne d'analyse devra déterminer la signature.

## 2. Import MP3 impossible

R10 affichait seulement :

```text
Le fichier ou les informations d’import sont invalides.
```

Plusieurs erreurs de téléversement étaient donc masquées.

R10.3 distingue désormais :

- MP3 trop volumineux pour la configuration PHP ;
- upload partiel ;
- upload invalide ;
- mauvais format ;
- fichier vide ;
- erreurs équivalentes sur la pochette.

Le contrôle MIME MP3 est aussi rendu compatible avec Windows/PHP : certains MP3 valides sont détectés comme :

```text
application/octet-stream
```

Cette valeur est acceptée uniquement pour un fichier portant l'extension `.mp3`.

Le MP3 reste stocké hors de `public/` dans :

```text
var/storage/audio/
```

## Vérifier la limite PHP

Si l'écran affiche :

```text
Le MP3 dépasse la taille maximale autorisée par PHP sur ce serveur.
```

exécuter :

```powershell
php -r "echo 'upload_max_filesize=',ini_get('upload_max_filesize'),PHP_EOL,'post_max_size=',ini_get('post_max_size'),PHP_EOL;"
```

Pour le serveur PHP intégré, un démarrage avec des limites adaptées peut être fait par exemple avec :

```powershell
php -d upload_max_filesize=128M -d post_max_size=132M -S 127.0.0.1:8501 -t H:\EZScore_v1\public
```

En production PHP-FPM/Apache, régler les mêmes directives dans la configuration PHP du serveur.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_IMPORT_FIX_AUTO_R10_3.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
```

Aucune migration Doctrine n'est nécessaire.

## Vérification

1. Ouvrir `/fr/import`.
2. Vérifier que `Signature` vaut `Auto`.
3. Choisir un MP3.
4. Importer.
5. En cas d'échec, le message doit maintenant indiquer la cause précise au lieu du message générique.
