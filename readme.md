# EZScore_v1 — R24.8 Launcher visuel

R24.6 supprimait les fenêtres PowerShell parasites, mais rendait le launcher trop silencieux.

R24.8 garde les consoles cachées et ajoute une vraie petite fenêtre Windows de démarrage.

Elle affiche en temps réel :

```text
Arrêt de l'ancien worker
Vérification du token
Vérification Python / RoFormer / CUDA
Démarrage du serveur PHP 127.0.0.1:8501
Attente du serveur web
Démarrage de EZScore Analysis Worker
Ouverture du navigateur
EZScore est prêt
```

avec une barre de progression et un journal de démarrage.

En cas d'erreur, la fenêtre reste ouverte et affiche le message au lieu de disparaître silencieusement.

En cas de succès, elle se ferme automatiquement environ 1,6 seconde après `EZScore est prêt`.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_8_LAUNCHER_PROGRESS_UI.zip" -C H:\EZScore_v1

php tests\launcher_ui_r24_8_contract.php
```

Puis :

```powershell
.\EZScore-Launcher.cmd
```

La console PowerShell ne doit pas rester ouverte.
Une fenêtre `EZScore Launcher` doit apparaître et montrer chaque étape.

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
