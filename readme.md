# EZScore_v1 — R32.3 ChordsLab UI / i18n

Ce correctif traite les défauts visibles après R32.

## 1. Workflow mal présenté avant ouverture d'un Lab

La feuille :

```text
workflow-tabs-r23-2.css
```

était chargée uniquement par certaines pages (`StemsLab`, `ChordsLab`) et pas par la page morceau.

R32.3 la charge désormais depuis `base.html.twig`, donc le workflow a le même rendu partout dès le premier affichage.

## 2. Libellés `chordslab.xxx` affichés à l'écran

Les traductions R32 sont dans :

```text
translations/chordslab.fr.yaml
translations/chordslab.en.yaml
```

donc dans le domaine Symfony `chordslab`.

Le template utilisait le domaine par défaut `messages`. R32.3 ajoute explicitement le domaine `chordslab` à tous les libellés concernés, y compris les flashes.

## 3. Signature `auto`

Le morceau Aline est actuellement en `auto`, mais la liste R32 commençait à `2/2`, ce qui affichait à tort `2/2` dans le sélecteur.

`auto` est maintenant la première valeur de la liste et reste sélectionné tant que l'analyse n'a pas déterminé ou que l'éditeur n'a pas imposé une signature.

## 4. Boutons du player

Les glyphes ajoutés dans ChordsLab dupliquaient ceux déjà présents dans certaines traductions Stems. Ils sont retirés.

## 5. Pourquoi le prompteur est vide

R32 n'invente volontairement aucun accord.

Le prompteur lit exclusivement `song_timeline_events` avec `event_type = chord`.

Si la table ne contient aucun accord pour le morceau, l'état vide est correct.

Le dépôt actuel contient le moteur STEMS / proxies de lecture, mais pas encore le moteur d'analyse harmonique qui doit alimenter la timeline. Cette étape sera R33.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R32_3_CHORDSLAB_UI_I18N_FIX.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r32_3_ui_i18n_fix.py

php -l .\src\Controller\SongLabController.php
php .\tests\r32_3_ui_i18n_contract.php
php bin\console lint:yaml translations
php bin\console lint:twig templates
php bin\console cache:clear
```

Attendu :

```text
7 R32.3 UI/i18n checks passed.
```

Puis `Ctrl+F5`.

Aucune migration Doctrine.
Aucune donnée musicale modifiée.
Aucun accord artificiel ajouté.
