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


## 11. Événements

Un événement organise un rendez-vous musical autour d'une date, d'un groupe, d'une playlist et de participants.

Cas d'usage :

```text
Événement : Répétition du 30/10/2026
Groupe : Formation A
Playlist : Répétition 30/10/2026
Mode : présentiel / à distance / hybride
Participants : membres du groupe
```

Un événement possède :

- un titre ;
- une description ;
- une date/heure de début ;
- une date/heure de fin facultative ;
- un mode `onsite`, `remote` ou `hybrid` ;
- un lieu facultatif ;
- une URL de session distante facultative ;
- un statut `draft`, `scheduled`, `cancelled` ou `completed` ;
- un créateur ;
- zéro ou un groupe ;
- zéro ou une playlist ;
- une collection de participants.

### 11.1 RSVP

La participation à un événement est indépendante de l'accès à la playlist.

États :

```text
invited
accepted
declined
maybe
```

L'appartenance à un groupe donne l'accès automatique aux playlists du groupe, mais une invitation à un événement peut demander une confirmation de présence.

### 11.2 Événement associé à un groupe

Lorsqu'un groupe est associé à l'événement :

- les membres actifs du groupe deviennent participants de l'événement ;
- aucune invitation de playlist n'est créée ;
- ils reçoivent l'invitation d'événement ;
- la réponse RSVP reste individuelle.

Si une playlist est associée à l'événement et n'est pas encore affectée au groupe, EZScore peut créer l'association `PlaylistGroup` lorsque l'organisateur possède les droits nécessaires.

### 11.3 Session hors groupe

Un événement peut être organisé sans groupe.

Lorsqu'une playlist est associée, les participants proposés doivent déjà avoir accès à cette playlist :

- propriétaire ;
- partage personnel accepté ;
- accès hérité via un groupe ;
- ou playlist publique.

L'événement ne doit pas servir à contourner l'ACL playlist.

## 12. Notifications d'événement

Canaux retenus :

```text
EZScore / in-app
Email
Partage utilisateur WhatsApp
Partage utilisateur Facebook
Partage utilisateur X
```

Le SMS n'est pas retenu.

### 12.1 Notification interne

Une invitation persistée dans `EventParticipant` apparaît dans la liste des invitations EZScore de l'utilisateur.

### 12.2 Email

L'ajout d'un participant déclenche un email d'invitation lorsque le transport mail est disponible.

L'échec du transport mail ne doit pas annuler la création de l'invitation persistée.

### 12.3 Réseaux sociaux

WhatsApp, Facebook et X sont des boutons de partage explicites déclenchés par l'utilisateur.

Ils ne constituent pas le canal métier principal et ne doivent pas être nécessaires au fonctionnement de l'événement.

Aucun numéro de téléphone n'est requis dans EZScore.

### 12.4 Web Push

Le Web Push/PWA reste le prochain canal automatique gratuit à ajouter.

Il devra utiliser :

- consentement explicite de l'utilisateur ;
- abonnement navigateur persistant ;
- VAPID ;
- Service Worker ;
- possibilité de désinscription.

Aucun faux push local ne doit être présenté comme un Web Push.

### 12.5 Telegram / Discord

Telegram Bot et Discord Webhook restent des connecteurs automatiques optionnels futurs par groupe.

Ils ne doivent pas modifier le cœur du modèle `Event`.

## 13. Ergonomie des événements

La même ergonomie de sélecteur modal utilisée pour les collections R18 est réutilisée :

- associer un groupe : sélection simple ;
- associer une playlist : sélection simple ;
- gérer les participants : sélection multiple avec checkbox ;
- recherche ;
- pagination serveur ;
- ajout/retrait groupés.

Le sélecteur générique doit donc supporter les modes `single` et `multiple`.
