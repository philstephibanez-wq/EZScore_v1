# EZScore_v1 — Groupes / Playlists CRUD sans modification du socle visuel

Base de référence obligatoire :

```text
e736731db363b6f614e8d050f4fab41a1e4f877b
EZScore_v1_LOCALIZED_URLS_NAV_HOVER OK
```

## Principe

Cette livraison ajoute uniquement la gestion des formulaires Groupes / Playlists en réutilisant les classes HTML/CSS déjà présentes dans EZScore_v1.

Aucun fichier CSS, aucun layout global, aucun `base.html.twig` et aucun JavaScript global ne sont modifiés.

Fonctions :

- modification et suppression des playlists ;
- modification et suppression des groupes ;
- ajout / modification / retrait des membres d'un groupe ;
- admin : tous les droits ;
- owner / manager : gestion selon les droits de la ressource ;
- suppression d'un groupe : suppression de ses playlists de groupe ;
- confirmations de suppression ;
- FR / EN ;
- conservation explicite de la locale `/fr` ou `/en` après les POST.

## Installation

Partir impérativement de la base stable :

```powershell
cd H:\EZScore_v1
git reset --hard e736731db363b6f614e8d050f4fab41a1e4f877b
```

Puis installer le ZIP :

```powershell
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_GROUPS_PLAYLISTS_FORMS_R3.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
php bin\console doctrine:schema:validate
php bin\console debug:router
```

Aucune migration.

Les routes attendues en plus des routes existantes sont :

```text
app_group_update
app_group_delete
app_group_member_delete
app_playlist_update
app_playlist_delete
```

## Contrôle visuel

Le rendu doit rester celui du commit `e736731...`. La livraison ne contient aucun fichier sous `public/assets/css/`, aucun `templates/base.html.twig` et aucun fichier JS.
