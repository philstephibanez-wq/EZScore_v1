# EZScore ACL — Symfony Security natif

## Principe

Le modèle reprend les concepts utiles d'OPUS ACL mais utilise uniquement Symfony Security.

- rôle global : `ROLE_READER`, `ROLE_EDITOR`, `ROLE_ADMIN`
- ressource : entité Doctrine
- privilège : `AclPrivilege`
- condition : Voter Symfony
- décision : `is_granted()` / `denyAccessUnlessGranted()`

## Playlist

Une playlist possède toujours un propriétaire humain.

`PLAYLIST_VIEW` est accordé si :

- Admin ;
- playlist publique ;
- utilisateur propriétaire ;
- invitation personnelle acceptée ;
- utilisateur membre d'au moins un groupe associé à la playlist.

Les privilèges de modification restent au propriétaire humain ou à l'Admin.

## Groupe

`GROUP_CREATE` : Editor ou Admin.

`GROUP_VIEW` : membre du groupe ou Admin.

`GROUP_EDIT`, `GROUP_MANAGE_MEMBERS`, `GROUP_MANAGE_PLAYLISTS` :
owner, manager ou Admin.

`GROUP_DELETE`, `GROUP_DELEGATE` :
owner ou Admin.

## Association Groupe / Playlist

`PlaylistGroup` matérialise le rattachement.

Il donne automatiquement `PLAYLIST_VIEW` aux membres du groupe.

Aucune `PlaylistInvitation` n'est créée pour cet accès.

## Partage personnel

`PlaylistInvitation` est réservé au partage direct utilisateur vers utilisateur.

États :

- pending
- accepted
- declined
- cancelled

Seul `accepted` accorde la visibilité.

## Chansons

La visibilité d'une playlist ne remplace jamais la politique d'accès aux chansons.
Chaque chanson affichée reste contrôlée par `SongVoter`.
