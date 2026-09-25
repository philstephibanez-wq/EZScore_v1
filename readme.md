# EZScore_v1 — R24.10 STEMS : progression utilisateur uniquement

La console Python/RoFormer est supprimée de la page STEMS.

Un utilisateur ne doit pas voir :

```text
Traceback
chemins Python
warnings Torch
commandes RoFormer
logs techniques
```

Ces informations restent disponibles côté fichiers/logs pour le diagnostic développeur, mais ne sont plus exposées dans l'interface.

## Interface pendant une extraction

La page montre uniquement :

```text
EN ATTENTE / EN COURS
étape courante
progression globale
progression moteur si disponible
temps de l'étape
heure de dernière activité
barre de progression
```

avec le message :

```text
Analyse en cours. Vous pouvez quitter cette page :
le traitement continue en arrière-plan.
```

## Fin du clignotement

Le polling continue toutes les 2 secondes uniquement pendant un job actif.

À la fin :

1. le polling est arrêté ;
2. un seul rechargement est effectué pour afficher les STEMS persistants ;
3. après ce rechargement, aucun hook de polling n'est présent puisque `job_active=false`.

Il n'y a donc plus de boucle de `window.location.reload()`.

## Confirmation de réanalyse

La modale EZScore intégrée de R24.7 est conservée.
Aucun `confirm()` natif Chrome n'est réintroduit.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_10_STEMS_PROGRESS_ONLY.zip" -C H:\EZScore_v1

php tests\stems_progress_only_r24_10_contract.php
php bin\console lint:twig templates
php bin\console cache:clear
```

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
