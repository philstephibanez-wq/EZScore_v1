# EZScore_v1 — R23.4 Console Python + worker STEM permanent

R23.4 complète R23.3 sur deux points seulement :

1. **console de diagnostic en direct dans la page STEMS** ;
2. **worker STEM permanent démarré automatiquement par Windows**.

Le périmètre musical reste strictement **STEMS only**.

## Console en direct

La page STEMS affiche maintenant :

```text
Console STEMS
```

avec la sortie réelle de :

```text
FFmpeg
BS-RoFormer
MelBand-RoFormer
worker Python
```

La console :

- lit le log toutes les 2 secondes ;
- affiche les dernières lignes sans recharger la page ;
- suit automatiquement le bas du log tant que l'utilisateur n'a pas remonté manuellement ;
- expose également l'état et la progression courante ;
- permet un téléchargement du fichier `.log`.

Le fichier persistant reste :

```text
var/storage/stems/song-{id}/{audio_sha256}/worker.log
```

Le endpoint ne renvoie que la fin du fichier afin d'éviter de charger un gros log complet à chaque rafraîchissement.

## Progression

Le rafraîchissement HTML complet toutes les 3 secondes est supprimé.

Le JavaScript met à jour en direct :

```text
état du job
pourcentage global
pourcentage moteur
étape
temps écoulé
heure de dernière activité
console Python
```

À la fin ou en cas d'échec, la page est rechargée une fois afin d'afficher l'état final et les lecteurs STEMS.

## Worker permanent

Le worker peut désormais fonctionner sans terminal PowerShell ouvert.

R23.4 installe une tâche Windows :

```text
EZScore STEM Worker
```

qui démarre à l'ouverture de session Windows et lance en arrière-plan :

```text
php bin/console app:stems:worker --sleep=2
```

Un superviseur PowerShell relance également le worker si le processus PHP s'arrête.

Log du superviseur :

```text
var/log/stem-worker-permanent.log
```

La séparation Python conserve son propre log par chanson.

## Installation R23.4

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_STEMS_CONSOLE_PERMANENT_WORKER_R23_4.zip" -C H:\EZScore_v1

python -m py_compile .\analysis\stems_only.py

Get-ChildItem src,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php bin\console lint:twig templates
php bin\console cache:clear

php tests\stems_console_worker_r23_4_contract.php
```

Aucune migration Doctrine.

## Installer le worker automatique

Toujours depuis :

```powershell
cd H:\EZScore_v1
```

exécuter :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install_stem_worker_task.ps1
```

Le script :

- crée ou remplace la tâche `EZScore STEM Worker` ;
- la configure pour le compte Windows courant ;
- démarre immédiatement le worker ;
- redémarre le worker après incident ;
- ne crée pas une deuxième instance si une instance tourne déjà.

## Vérifier le worker permanent

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\status_stem_worker_task.ps1
```

Attendu notamment :

```text
Task:       EZScore STEM Worker
State:      Running
```

et les dernières lignes de :

```text
var/log/stem-worker-permanent.log
```

## Arrêter / retirer le worker permanent

Uniquement si nécessaire :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\uninstall_stem_worker_task.ps1
```

## Premier test

1. Installer la tâche permanente.
2. Vérifier qu'elle est `Running`.
3. Ouvrir une chanson.
4. Ouvrir `2 · STEMS`.
5. Cliquer sur la séparation.
6. Ne lancer aucun PowerShell supplémentaire.
7. Le job doit passer automatiquement de `EN ATTENTE` à `EN COURS`.
8. La console doit commencer à afficher les commandes et sorties Python.
9. La progression doit évoluer en direct.
10. En cas d'échec, télécharger le log depuis la page.

## Toujours absent

R23.4 ne lance toujours aucun :

```text
Whisper
paroles
phonèmes
accords
tempo
beats
mesures
structure
MIDI
conducteur
karaoké
```
