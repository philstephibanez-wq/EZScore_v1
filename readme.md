# EZScore_v1 — R24.18 État du serveur dans Analysis Worker

Le Worker affiche maintenant en permanence l'état du serveur local EZScore.

## Affichage

Dans `Connexion / Runtime` :

```text
API EZScore       http://127.0.0.1:8501
Python STEM       H:\EZScore_v1\.venv-py313\Scripts\python.exe
CUDA / GPU        NVIDIA GeForce RTX 2060
Serveur local     ACTIF · 127.0.0.1:8501 · PID 16228
Canal             RX/TX OK
```

Si le serveur est arrêté :

```text
Serveur local     ARRÊTÉ · 127.0.0.1:8501
```

Le contrôle est refait automatiquement toutes les 2 secondes.

Le test ne se contente pas du fichier PID : il vérifie aussi que le serveur HTTP répond réellement sur :

```text
http://127.0.0.1:8501/fr/login
```

## Bouton Ouvrir EZScore

Le bouton ouvre désormais l'URL utilisateur correcte :

```text
https://ezscore.logandplay.com/fr/catalog
```

L'API locale et l'URL navigateur sont donc clairement séparées.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_18_WORKER_SERVER_STATUS.zip" -C H:\EZScore_v1

python -m py_compile .\worker_app\ezscore_analysis_worker.pyw
php tests\worker_server_status_r24_18_contract.php
```

Fermer puis relancer `EZScore Analysis Worker` pour charger cette version.

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
