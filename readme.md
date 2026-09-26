# EZScore_v1 — R25.5 Chargement audio paresseux

Le problème de navigation lente depuis la page STEMS est identifié.

## Cause

Le mixer R25 lançait immédiatement, dès l'ouverture de la page :

```text
9 requêtes audio
+ téléchargement intégral
+ decodeAudioData des 9 pistes
```

Le serveur local actuel est :

```powershell
php -S 127.0.0.1:8501 -t H:\EZScore_v1\public
```

Ce serveur de développement est un mauvais endroit pour saturer immédiatement la connexion avec plusieurs gros WAV.

Conséquence visible :

```text
clic Répertoire / Retour / Modifier
→ le clic fonctionne
→ mais la navigation semble bloquée longtemps
```

## Correction R25.5

Aucune piste n'est maintenant téléchargée au chargement de la page.

Le mixer charge seulement les pistes actives au premier clic sur Lecture.

Par défaut :

```text
Original = actif
les STEMS = inactifs
```

Donc le premier Play charge seulement `Original`.

Si une piste est activée pendant la lecture :

```text
ON
→ téléchargement de cette piste seulement
→ décodage
→ démarrage à la position courante
```

Les autres pistes ne sont jamais téléchargées tant qu'elles ne sont pas utilisées.

La synchronisation Web Audio, le mixage temps réel, l'EQ et la persistence restent inchangés.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R25_5_STEMS_LAZY_LOADING.zip" -C H:\EZScore_v1

node --check .\public\assets\js\stems-mixer.js
php .\tests\stems_lazy_loading_r25_5_contract.php

php bin\console cache:clear
```

Puis :

```text
Ctrl + F5
```

Test :

```text
ouvrir STEMS
→ cliquer immédiatement Répertoire / Retour / Modifier
```

La navigation doit partir immédiatement, sans attendre le chargement des 9 audios.

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
