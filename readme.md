# EZScore_v1 — R23.2 Workflow intuitif

Ce correctif ne touche pas au moteur STEMS.

Il met en place le workflow validé :

## Nouvelle chanson

```text
1 · IMPORTER
2 · STEMS
```

`STEMS` est visible mais désactivé avant validation de l'import.

## Chanson existante

```text
1 · MODIFIER
2 · STEMS
```

Le bouton `Modifier` redondant du workspace est supprimé au profit de l'onglet canonique.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_WORKFLOW_R23_2.zip" -C H:\EZScore_v1

php bin\console lint:twig templates
php bin\console cache:clear

php tests\workflow_tabs_r23_2_contract.php
```

Aucune migration Doctrine.
Aucune nouvelle analyse.
