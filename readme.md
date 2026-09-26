# EZScore_v1 — R33 Prompteur ChordsLab éditable

R33 livre le prompteur réellement alimenté par l'audio et éditable.

## Inclus

- bouton **Analyser les accords** ;
- détection beats + accords + tonalité ;
- modes Débutant / Intermédiaire / Expert ;
- écriture dans la timeline canonique ;
- affichage `[Em---]` dérivé de la timeline ;
- accord actif répété au début de la mesure suivante ;
- surbrillance du beat courant synchronisée au player existant ;
- édition inline avec persistance dans `override_value` ;
- reset des overrides déjà présent ;
- capo temps réel uniquement sur la forme guitare ;
- recomposition temps réel du prompteur quand la signature change ;
- diagramme au-dessus de l'accord actif ;
- libellé **Afficher accords guitare** ;
- Pistes / Effets restent repliés par défaut ;
- responsive PC / tablette / smartphone.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R33_EDITABLE_CHORD_PROMPTER.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r33_editable_chord_prompter.py

H:\EZScore_v1\.venv-py313\Scripts\python.exe -m pip install -r .\analysis\requirements-chords.txt

php -l .\src\Service\ChordTimelineAnalysisService.php
php -l .\src\Controller\SongLabController.php
php -l .\src\Domain\Song\SongTimelineEventRepository.php
node --check .\public\assets\js\chordslab.js
H:\EZScore_v1\.venv-py313\Scripts\python.exe -m py_compile .\analysis\chord_timeline_analysis.py

php .\tests\r33_editable_chord_prompter_contract.php
php bin\console lint:yaml translations
php bin\console lint:twig templates
php bin\console cache:clear
```

Attendu :

```text
16 R33 editable-prompter checks passed.
```

Puis Ctrl+F5, ouvrir ChordsLab et cliquer **Analyser les accords**.

Aucune migration Doctrine supplémentaire.
