# EZScore_v1 — R24.9 Launcher rapide et visible

R24.8 avait deux défauts observés en réel :

1. une exception WinForms/.NET `Impossible d'appeler une méthode dans une expression Null` ;
2. un délai trop long avant l'apparition de `EZScore Analysis Worker`.

## Cause de l'exception

Le timer de fermeture automatique était capturé dans une portée locale puis réutilisé depuis le callback WinForms. Dans certaines exécutions, le callback retrouvait une variable nulle.

R24.9 utilise une variable `$script:closeTimer` et teste explicitement toutes les références avant d'appeler `Stop()`, `Dispose()` ou `Close()`.

## Démarrage plus rapide

Le launcher ne relance plus `prepare_analysis_runtime.ps1` à chaque démarrage.

Cette opération est une préparation/installation du runtime et non une étape nécessaire à chaque lancement.

Ordre R24.9 :

```text
Launcher visible immédiatement
→ désactivation ancien worker
→ token
→ serveur PHP 127.0.0.1:8501
→ attente serveur
→ ouverture immédiate du Worker desktop
→ le Worker vérifie lui-même Python / RoFormer / CUDA
→ navigateur
```

La fenêtre Worker doit donc apparaître beaucoup plus tôt.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_9_LAUNCHER_FAST_VISIBLE_FIX.zip" -C H:\EZScore_v1

php tests\launcher_ui_r24_9_contract.php
```

Puis :

```powershell
.\EZScore-Launcher.cmd
```

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
