# EZScore_v1 — R25.4 Original audio + navigation STEMS

Deux corrections cumulatives.

## 1. Piste Original inaudible

Le mixer chargeait 8 pistes sur 9 : la piste manquante était `Original`.

Cause :

`SongImportStorage` persiste `audioStoragePath` sous forme relative :

```text
var/storage/audio/<sha256>.<ext>
```

La route `/stems/original` utilisait directement cette valeur avec `is_file()` puis `BinaryFileResponse`.

R25.4 résout explicitement ce chemin depuis :

```text
%kernel.project_dir%
```

donc, sur cette installation :

```text
H:\EZScore_v1\var\storage\audio\<sha256>.<ext>
```

La réponse conserve le MIME original et reste protégée par les droits Editor/Admin du morceau.

## 2. Navigation latérale depuis STEMS

Le correctif de couche du panneau gauche est inclus également :

```text
contenu STEMS
< backdrop
< drawer
< topbar
```

Ainsi les liens Répertoire / Playlists / Groupes / Sessions restent cliquables depuis STEMS.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R25_4_STEMS_ORIGINAL_DRAWER_FIX.zip" -C H:\EZScore_v1

php -l .\src\Controller\SongStemController.php
php .\tests\stems_original_drawer_r25_4_contract.php

php bin\console cache:clear
```

Puis dans Chrome :

```text
Ctrl + F5
```

Sur la page STEMS, le mixer doit passer de :

```text
8/9 pistes chargées
```

à :

```text
9/9 pistes chargées
```

et `Original` doit devenir audible.

Aucune migration Doctrine.
Aucun changement du moteur de séparation STEMS.
