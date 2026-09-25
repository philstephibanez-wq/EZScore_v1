# EZScore_v1 — R23.1 STEM route fix

R23 ajoutait `SongStemController.php` mais `config/routes.yaml` n'importait pas ce contrôleur.

Conséquence :

```text
RouteNotFoundException
Unable to generate a URL for the named route "app_song_stems"
```

R23.1 ajoute l'import localisé :

```yaml
localized_song_stems:
    resource: ../src/Controller/SongStemController.php
    type: attribute
    prefix:
        fr: /fr
        en: /en
```

Aucun autre comportement n'est modifié.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_STEMS_ROUTE_FIX_R23_1.zip" -C H:\EZScore_v1

php bin\console lint:yaml config
php bin\console cache:clear

php bin\console debug:router | Select-String "song_stems"

php tests\stems_route_r23_1_contract.php
```

Attendu dans `debug:router` :

```text
app_song_stems
app_song_stems_generate
app_song_stem_audio
```

Pas de migration Doctrine.
