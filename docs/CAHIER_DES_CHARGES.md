# EZScore_v1 — Cahier des charges fonctionnel

Version de référence : R18.

## Rôles globaux

Les rôles sont définis par Symfony :

- `ROLE_READER`
- `ROLE_EDITOR`
- `ROLE_ADMIN`

L'attribution d'un rôle à un utilisateur est persistée dans `users.roles`.

Hiérarchie :

```text
ROLE_ADMIN
  -> ROLE_EDITOR
       -> ROLE_READER
```

## Lecteur

Un Lecteur crée des playlists personnelles.

Une playlist personnelle :

- appartient toujours à un utilisateur humain ;
- contient une collection ordonnée de chansons ;
- peut être partagée avec d'autres utilisateurs déjà inscrits ;
- le partage personnel exige une invitation ;
- l'invité doit accepter ou refuser ;
- aucune visibilité n'est accordée tant que l'invitation reste en attente ;
- une invitation acceptée donne un accès en lecture ;
- le propriétaire peut retirer le partage ;
- l'invité peut quitter le partage.

La sélection des utilisateurs se fait par nom affiché. L'identifiant persistant est `user_id`.

## Éditeur et groupes musicaux

Un Éditeur hérite des droits du Lecteur et peut créer un ou plusieurs groupes.

Cas d'usage principal : un Éditeur est chef de formation d'un ou plusieurs groupes musicaux.

Exemple :

```text
Groupe : Formation A
Membres :
- Alice
- Bob
- Chloé

Playlist : Répétition samedi 30/08/2026
1. Chanson A
2. Chanson B
...
n. Chanson N
```

Le chef de formation :

- crée le groupe ;
- compose la collection de membres ;
- crée ou possède des playlists ;
- affecte une ou plusieurs playlists au groupe ;
- peut retirer une playlist du groupe sans la supprimer.

## Accès automatique par groupe

Une playlist affectée à un groupe est visible automatiquement par tous les membres du groupe.

Aucune invitation playlist n'est créée et aucune confirmation n'est demandée.

```text
Partage personnel
= PlaylistInvitation
= acceptation requise

Partage par groupe
= GroupMember + PlaylistGroup
= accès automatique
= aucune invitation
```

Retirer un utilisateur du groupe retire immédiatement son accès hérité.

Retirer une playlist du groupe retire l'accès hérité mais conserve la playlist.

## Collections métier

```text
UserGroup
  -> collection de GroupMember
  -> collection de Playlist via PlaylistGroup

Playlist
  -> owner User
  -> collection de PlaylistGroup
  -> collection ordonnée de Song via PlaylistItem
  -> collection de PlaylistInvitation pour le partage personnel
```

Une playlist peut être affectée à plusieurs groupes.

## Propriété d'une playlist

Une playlist possède toujours un propriétaire humain `owner_user_id`.

Le rattachement à un groupe est une association, pas une propriété.

Le modèle historique `owner_type / owner_id` est supprimé.

## ACL

Symfony Security est l'unique moteur d'autorisation.

Les règles passent par :

- `PlaylistVoter`
- `GroupVoter`
- `SongVoter`

Les contrôleurs utilisent `denyAccessUnlessGranted()`.
Les templates utilisent `is_granted()`.

Le partage d'une playlist ne contourne jamais les droits propres aux chansons.

## Ergonomie des collections

Les listbox volumineuses sont remplacées par un sélecteur modal générique avec :

- recherche ;
- filtres métier ;
- pagination serveur ;
- cases à cocher ;
- sélection multiple ;
- sélection de la page courante ;
- ajout groupé ;
- retrait groupé.

### Groupe > Membres

Filtres :

- nom affiché ;
- rôle global ;
- actif / inactif ;
- présent / absent selon l'action.

Actions :

- ajouter plusieurs membres ;
- retirer plusieurs membres ;
- changer le rôle contextuel `member / manager / owner`.

Le groupe doit toujours conserver au moins un `owner`.

### Groupe > Playlists

Filtres :

- nom ;
- visibilité ;
- déjà affectée / non affectée.

Actions :

- affecter plusieurs playlists ;
- retirer plusieurs playlists.

Un Éditeur ne peut affecter que les playlists qu'il peut modifier. L'Admin peut tout administrer.

### Playlist > Chansons

Filtres :

- titre / artiste ;
- statut ;
- déjà présente / absente.

Actions :

- ajouter plusieurs chansons ;
- retirer plusieurs chansons ;
- conserver l'ordre explicite.

### Playlist > Partage personnel

Filtres :

- nom affiché ;
- rôle global ;
- état du compte ;
- déjà invité / non invité.

Actions :

- inviter plusieurs utilisateurs ;
- retirer plusieurs invitations ou partages.

## Back-office Admin

Pour chaque groupe :

- propriétaires ;
- gestionnaires ;
- nombre de membres ;
- nombre de playlists ;
- date ;
- description.

Pour chaque playlist :

- propriétaire humain ;
- groupes associés ;
- créateur ;
- visibilité ;
- nombre de chansons ;
- états de partage ;
- date.

## Doctrine

Toutes les modifications de structure passent par des migrations.

Ne jamais utiliser :

```text
doctrine:schema:update --force
```

Après migration, `doctrine:schema:validate` doit être vert et
`doctrine:schema:update --dump-sql` ne doit proposer aucun SQL.
