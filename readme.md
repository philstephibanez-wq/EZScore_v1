# EZScore_v1 — CRUD Playlists / Groupes

Base GitHub vérifiée :

```text
e736731db363b6f614e8d050f4fab41a1e4f877b
```

## Playlists

Ajout : modification nom/description, propriétaire personnel/groupe, visibilité, suppression.

Permissions :
- Admin : tous les droits sur toutes les playlists.
- Playlist personnelle : propriétaire.
- Playlist de groupe : owner ou manager du groupe.

## Groupes

Ajout : modification nom/description, suppression, retrait d'un membre.

Permissions :
- Admin : tous les droits sur tous les groupes et membres.
- Owner / manager : modification du groupe et gestion des membres.
- Suppression du groupe : admin ou owner.
- Un manager non-admin ne peut pas retirer un owner ni promouvoir quelqu'un owner.

La création d'un groupe reste réservée à l'admin, comme actuellement.

## Suppression d'un groupe

`Playlist.ownerId` n'est pas une FK Doctrine. Les playlists appartenant au groupe sont donc supprimées explicitement avant le groupe pour éviter des données orphelines. Les memberships sont aussi supprimés explicitement.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_PLAYLISTS_GROUPS_CRUD.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
php bin\console doctrine:schema:validate
php bin\console debug:router
```

Aucune migration.
