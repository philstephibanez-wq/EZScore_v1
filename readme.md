# EZScore_v1 — R24.6 Launcher 8501 + fenêtres console masquées

Correction suite au test réel.

## Serveur EZScore_v1

La commande de référence est maintenant exactement :

```powershell
php -S 127.0.0.1:8501 -t H:\EZScore_v1\public
```

Le launcher démarre ce serveur via `Start-Process` en fenêtre cachée, avec logs :

```text
var\log\ezscore-web.out.log
var\log\ezscore-web.err.log
```

Le Worker desktop utilise désormais par défaut :

```text
http://127.0.0.1:8501
```

au lieu de `8000`.

## Fenêtres PowerShell / consoles noires

Deux changements :

1. `EZScore-Launcher.cmd` délègue immédiatement à `EZScore-Launcher.vbs`, qui lance PowerShell en mode caché.
2. Les subprocess Python/RoFormer lancés par l'application desktop utilisent `CREATE_NO_WINDOW` sous Windows.

La vraie fenêtre **EZScore Analysis Worker** reste visible. Les consoles auxiliaires ne doivent plus apparaître.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_6_LAUNCHER_8501_NO_CONSOLE.zip" -C H:\EZScore_v1

python -m py_compile .\worker_app\ezscore_analysis_worker.pyw

php tests\launcher_r24_6_contract.php
```

Puis lancer simplement :

```text
H:\EZScore_v1\EZScore-Launcher.cmd
```

Le résultat attendu :

```text
serveur PHP 127.0.0.1:8501 (caché)
+
fenêtre EZScore Analysis Worker
+
navigateur sur http://127.0.0.1:8501/fr/login
```

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
