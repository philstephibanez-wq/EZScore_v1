# EZScore_v1 R7.4.1 — correctif langue utilisateur

Le lot précédent n'affichait pas la colonne demandée. Ce correctif la rend explicite.

## Administration > Utilisateurs

Chaque ligne contient maintenant une liste `FR / EN` entre Rôle et Actif.
La création d'un utilisateur contient aussi une liste de langue, avec FR sélectionné par défaut.

## Profil

Nouvelle route `/profile` permettant à l'utilisateur connecté de modifier sa propre langue.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R7_4_1_USER_LANGUAGE_FIX.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
php bin\console doctrine:schema:validate
```

Aucune migration.
