# EZScore_v1 — R28.1 correctif installateur

R28 échouait avant toute modification avec :

```text
expected source block not found
```

Cause : l'installateur R28 cherchait une chaîne contenant des `\n` littéraux au lieu de vrais retours à la ligne.

R28.1 remplace ce mécanisme fragile par un patch structurel et idempotent.

## Effet fonctionnel

Après application :

```text
STEMS persistants
- lead_vocals
- backing_vocals
- drums
- bass
- guitar
- piano
- other
```

`vocals.wav` reste uniquement un intermédiaire temporaire de MelBand puis est supprimé avant publication du run.

Le player ne génère plus `vocals.opus` et n'affiche plus `Voix — global`.

## Installation

Le premier R28 ayant échoué avant modification, il n'y a rien à restaurer.

Fermer le Worker Desktop puis :

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R28_1_REMOVE_GLOBAL_VOCALS_FIX.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r28_remove_global_vocals.py

H:\EZScore_v1\.venv-py313\Scripts\python.exe -m py_compile .\analysis\stems_only.py
H:\EZScore_v1\.venv-py313\Scripts\python.exe -m py_compile .\analysis\build_playback_proxies.py

php -l .\src\Service\SongStemStorage.php
php -l .\src\Service\SongStemPlaybackStorage.php
php -l .\src\Controller\SongStemController.php

php .\tests\r28_1_global_vocals_contract.php
php bin\console lint:twig templates
php bin\console cache:clear
```

Attendu :

```text
11 R28.1 checks passed.
```

## Nettoyage disque

D'abord en dry-run :

```powershell
H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\cleanup_redundant_global_vocals.py
```

Puis suppression réelle :

```powershell
H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\cleanup_redundant_global_vocals.py --apply
```

Le nettoyage ne supprime un ancien `vocals.wav` / `vocals.opus` que si le couple `lead_vocals` + `backing_vocals` correspondant existe et est non vide.

Aucune migration Doctrine.
