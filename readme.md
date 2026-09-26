# EZScore_v1 — R29 Master FX + mixer compact responsive

R29 remplace l'EQ par piste par une chaîne d'effets globale.

```text
STEMS / Original
→ Volume individuel
→ Bus master
→ Graves 110 Hz
→ Médiums 900 Hz / Q 1
→ Aigus 3,6 kHz
→ Compresseur global
→ Limiteur fixe -1 dB
→ Volume master
→ Sortie
```

## Ergonomie

Desktop : `Nom | Actif | Volume`, hauteur cible 42 px.

Tablette : 3 colonnes conservées, aucun min-width de 1100 px, Master FX réorganisé sur plusieurs colonnes.

Smartphone : en-tête masqué, chaque piste sur 2 lignes compactes (nom/activation puis volume), contrôles tactiles agrandis, Master FX sur une colonne, transport réorganisé. Un breakpoint supplémentaire existe pour les écrans <= 390 px.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R29_MASTER_FX_COMPACT_RESPONSIVE.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r29_master_fx_compact_mixer.py

node --check .\public\assets\js\audio\ezscore-audio-engine.js
node --check .\public\assets\js\stems-mixer.js

php -l .\src\Controller\SongStemController.php
php .\tests\r29_master_fx_contract.php
php bin\console lint:yaml translations
php bin\console lint:twig templates
php bin\console cache:clear
```

Attendu :

```text
18 R29 master-FX responsive checks passed.
```

Puis `Ctrl + F5`.

Aucune migration Doctrine. Le Worker Desktop, les STEMS et les proxies Opus ne sont pas régénérés.
