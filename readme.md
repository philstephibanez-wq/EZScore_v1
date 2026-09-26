# EZScore_v1 — R30 CDC + cahier de recette ChordsLab

Ce livrable met à jour :

- `docs/CAHIER_DES_CHARGES.md`
- `recette.md`

Il documente la spécification consolidée du workflow :

```text
Import
Analyse
Édition
StemsLab
ChordsLab
LyricsLab
Publication
```

et ajoute les exigences détaillées pour :

- timeline comme source de vérité ;
- prompteur harmonique synchronisé ;
- notation compacte `[Em---]`, etc. ;
- réaffichage explicite d'un accord qui continue sur la mesure suivante ;
- diagramme au-dessus de l'accord courant ;
- édition d'accord en place + persistance ;
- reset des accords ;
- capo EZScore = simplification de doigtés uniquement ;
- tonalité réelle visible dans le cartouche ;
- transposition future séparée du capo ;
- signatures rythmiques extensibles, y compris 7/4, 6/8, 13/16, etc. ;
- niveaux Débutant / Intermédiaire / Expert ;
- réutilisation du player existant ;
- Pistes et chaîne d'effets repliées par défaut ;
- ergonomie PC / tablette / smartphone ;
- plan de recette fonctionnelle complet et non-régression.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R30_CDC_RECETTE_CHORDSLAB.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r30_cdc_recette_chordslab.py
```

Validation :

```powershell
H:\EZScore_v1\.venv-py313\Scripts\python.exe .\tests\test_r30_cdc_recette.py
```

Attendu :

```text
22 R30 CDC/recette checks passed.
```

Le script est idempotent et crée une sauvegarde sous :

```text
var\backup\r30-cdc-recette-YYYYMMDD-HHMMSS
```

Aucune migration Doctrine.
Aucun code métier n'est modifié par ce livrable documentaire.
