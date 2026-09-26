# EZScore_v1 — R31 Workflow Labs

R31 implémente le nouveau workflow visible :

```text
Import
Analyse
Édition
StemsLab
ChordsLab
LyricsLab
Publication
```

Ce livrable pose le **workflow navigable et responsive**. Il ne prétend pas encore implémenter le moteur complet du prompteur ChordsLab.

## Changements

- `MODIFIER` devient `ÉDITION`.
- `STEMS` devient `StemsLab`.
- Ajout des étapes `Analyse`, `ChordsLab`, `LyricsLab`, `Publication`.
- Ajout de routes/pages shell pour ces nouvelles étapes.
- ChordsLab montre un aperçu de la future notation compacte.
- ACL identiques à StemsLab : Admin ou Éditeur propriétaire.
- Responsive :
  - PC : 7 colonnes ;
  - tablette : 4 colonnes ;
  - smartphone : 2 colonnes ;
  - aucune largeur desktop forcée.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R31_LABS_WORKFLOW.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r31_labs_workflow.py

php -l .\src\Controller\SongLabController.php
php .\tests\r31_workflow_contract.php

php bin\console lint:yaml translations
php bin\console lint:twig templates
php bin\console debug:router | Select-String "song_(analysis_lab|chordslab|lyricslab|publication_lab)"
php bin\console cache:clear
```

Attendu :

```text
12 R31 workflow checks passed.
```

Puis `Ctrl + F5`.

Aucune migration Doctrine.
Aucun STEM n'est régénéré.
Le Worker Desktop n'est pas modifié.
