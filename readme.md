# EZScore_v1 — R27.2 Desktop Worker + proxies Opus

Le job était bien lancé mais aucun player n'apparaissait.

## Cause

Le Worker Desktop lance directement `analysis/stems_only.py`, puis appelle immédiatement
`/internal/analysis/desktop/jobs/{id}/complete`.

Il ne passe donc pas par `App\Service\SongStemWorker`, où R27 avait ajouté la génération Opus.

R27.2 corrige le Worker Desktop réellement utilisé.

## Installation

Fermer d'abord EZScore Analysis Worker.

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R27_2_DESKTOP_WORKER_OPUS_FIX.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r27_2_worker_proxy_fix.py

H:\EZScore_v1\.venv-py313\Scripts\python.exe -m py_compile .\worker_app\ezscore_analysis_worker.pyw
H:\EZScore_v1\.venv-py313\Scripts\python.exe -m py_compile .\analysis\build_playback_proxies.py

php -l .\src\Controller\SongStemController.php
php .\tests\r27_2_desktop_worker_contract.php

php bin\console cache:clear
```

Attendu :

```text
11 R27.2 desktop-worker checks passed.
```

Relancer le Worker Desktop puis recliquer `Générer les pistes de lecture`.

Le Worker doit afficher :

```text
Commande STEMS lancée.
Génération des proxies Opus 192 kb/s lancée.
Proxies Opus générés.
Job #... terminé.
```

Vérification :

```powershell
Get-ChildItem H:\EZScore_v1\var\storage\stems -Recurse -Filter *.opus |
    Select-Object FullName,Length
```

Il doit y avoir 9 fichiers `.opus` pour le run courant.

Le flash littéral `stems.playback.queued` est également corrigé.

Aucune migration Doctrine.
Aucune nouvelle séparation STEMS nécessaire.
