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


## 11. Événements et type Session

`Event` reste le concept technique générique. L'interface expose actuellement un seul type métier : `Session`.

```text
Event
└─ type = session
```

D'autres types pourront être ajoutés ultérieurement sans remettre en cause le modèle.

Une Session organise un rendez-vous musical autour d'une date, d'un groupe, d'une playlist et de participants.

Cas d'usage :

```text
Session : Répétition du 30/10/2026
Groupe : Formation A
Playlist : Répétition 30/10/2026
Mode : présentiel / à distance / hybride
Participants : membres du groupe
```

Un `Event` de type `session` possède :

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

La participation à une Session est indépendante de l'accès à la playlist.

États :

```text
invited
accepted
declined
maybe
```

L'appartenance à un groupe donne l'accès automatique aux playlists du groupe, mais une invitation à un événement peut demander une confirmation de présence.

### 11.2 Session associée à un groupe

Lorsqu'un groupe est associé à la Session :

- les membres actifs du groupe deviennent participants de l'événement ;
- aucune invitation de playlist n'est créée ;
- ils reçoivent l'invitation de Session ;
- la réponse RSVP reste individuelle.

Si une playlist est associée à l'événement et n'est pas encore affectée au groupe, EZScore peut créer l'association `PlaylistGroup` lorsque l'organisateur possède les droits nécessaires.

### 11.3 Session hors groupe

Une Session peut être organisée sans groupe.

Lorsqu'une playlist est associée, les participants proposés doivent déjà avoir accès à cette playlist :

- propriétaire ;
- partage personnel accepté ;
- accès hérité via un groupe ;
- ou playlist publique.

La Session ne doit pas servir à contourner l'ACL playlist.

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


## 14. Scalabilité globale des listes et écrans de gestion

EZScore doit rester exploitable avec des centaines ou milliers d'entités.

Aucune page de gestion ne doit reposer sur un scroll continu de toutes les lignes ou cartes disponibles.

### 14.1 Principe général

Les écrans suivants utilisent systématiquement :

- recherche texte serveur ;
- index alphabétique A-Z ;
- filtres métier contextuels ;
- pagination serveur ;
- compteur de résultats ;
- conservation des filtres pendant la navigation ;
- aperçu limité pour les collections imbriquées volumineuses ;
- sélecteur modal paginé pour les ajouts/retraits massifs.

Le catalogue Chansons sert de référence ergonomique pour la recherche et l'index alphabétique.

### 14.2 Administration des utilisateurs

Le back-office Utilisateurs doit filtrer par :

- nom affiché ;
- e-mail ;
- lettre initiale ;
- rôle global ;
- compte actif / inactif ;
- compte Google lié / local ;
- langue.

La liste est paginée côté serveur.

Une modification ou suppression d'un utilisateur conserve le contexte de filtre et la page courante.

### 14.3 Administration des groupes

Le back-office global des groupes doit permettre :

- recherche par nom ou description ;
- recherche par membre ;
- recherche par e-mail de membre ;
- filtre par rôle contextuel `owner / manager / member` ;
- index alphabétique ;
- pagination serveur.

La page Groupes accessible aux utilisateurs autorisés doit aussi être paginée.

Pour éviter une page gigantesque, chaque carte groupe n'affiche qu'un aperçu des membres et playlists lorsque aucun filtre précis n'est actif.

Les collections complètes continuent à être administrées via le sélecteur modal paginé.

### 14.4 Administration des playlists

Les playlists sont filtrables par :

- nom ;
- description ;
- propriétaire ;
- groupe lié ;
- lettre initiale ;
- portée : mes playlists / via mes groupes / partagées / publiques ;
- chanson contenue par titre ou interprète.

La liste principale est paginée côté serveur.

Les chansons d'une playlist sont présentées sous forme d'aperçu limité lorsqu'aucune recherche chanson n'est active.

Les partages personnels sont présentés par compteurs, pas par une liste illimitée de noms. Leur gestion complète se fait via le sélecteur modal.

### 14.5 Sessions

La liste des Sessions est filtrable par :

- titre ;
- groupe ;
- playlist ;
- créateur ;
- lettre initiale ;
- statut ;
- mode présentiel / distant / hybride ;
- période à venir / passée / toutes.

La liste est paginée côté serveur.

La liste des participants d'une Session possède sa propre recherche, son index alphabétique, son filtre RSVP et sa pagination serveur.

### 14.6 Collections imbriquées

Une collection imbriquée ne doit jamais provoquer un scroll de plusieurs centaines de lignes dans une carte.

Règle :

```text
Liste principale -> pagination serveur
Collection imbriquée -> aperçu borné
Gestion complète -> recherche + picker/pagination
```

L'interface doit toujours afficher le nombre total d'éléments afin que l'utilisateur sache qu'il voit un aperçu.

### 14.7 Performance

Les contrôleurs ne doivent pas charger toutes les entités en mémoire pour ensuite les filtrer en PHP lorsqu'une requête Doctrine peut appliquer les ACL et filtres côté serveur.

Les requêtes de liste doivent :

- filtrer en SQL/DQL ;
- utiliser `COUNT(DISTINCT ...)` pour les jointures ;
- limiter les résultats avant hydratation ;
- éviter les listes HTML de centaines d'options ;
- ne charger les collections associées que pour la page courante.


## 15. Répertoire administrateur et ergonomie personnelle

### 15.1 Répertoire = back-office chansons pour l'Admin

Lorsqu'un Admin consulte le Répertoire, il dispose d'outils de back-office directement sur les morceaux.

Fonctions obligatoires :

- recherche par titre, interprète, auteur, compositeur ou éditeur ;
- tri par titre ou interprète ;
- index alphabétique ;
- filtre par statut ;
- filtre par éditeur ;
- pagination serveur ;
- changement de statut ;
- réattribution à un autre Éditeur actif ;
- suppression d'un morceau ;
- conservation du contexte de recherche/filtres après une action.

Le back-office doit permettre notamment de revenir explicitement au statut :

```text
Imported
```

depuis `Editing`, `Published` ou `Analyzed`.

Le statut `Analyzed` reste réservé à la chaîne d'analyse : il peut être conservé lorsqu'il existe déjà, mais ne doit pas être fabriqué manuellement par le back-office.

Une remise à `Imported` signifie fonctionnellement que le morceau doit pouvoir repasser par la chaîne d'analyse. Elle remet également `published_at` à `NULL`.

### 15.2 Modération du répertoire

La modération R21 s'appuie sur les états métier existants :

```text
Imported
Analyzed
Editing
Published
```

et sur la responsabilité éditoriale :

```text
Song.editor
```

Aucun état de modération parallèle n'est créé à ce stade.

L'Admin peut donc :

- dépublier ;
- repasser en édition ;
- remettre en importé ;
- publier ;
- changer l'Éditeur responsable ;
- supprimer.

### 15.3 Suppression

La suppression d'un morceau est réservée à l'Admin.

Les associations Doctrine configurées en cascade, notamment notes, jobs d'analyse et éléments de playlist, suivent leurs règles de suppression.

La suppression de fichiers physiques mutualisés par hash ne doit pas être faite aveuglément lors de cette action.

### 15.4 Profil personnel

`Profil` n'est pas une rubrique d'administration.

Il représente le compte de l'utilisateur connecté, y compris pour un Admin.

Dans le menu latéral :

- `Profil` est retiré de la navigation métier principale ;
- la carte d'identité de l'utilisateur dans le pied du menu devient le point d'accès à son Profil ;
- la section `Administration` reste réservée aux fonctions de back-office.

### 15.5 Aperçu de pochette

Lors de l'import ou de l'édition d'un morceau, le choix d'une pochette doit produire un aperçu local immédiat avant validation.

L'aperçu :

- utilise le fichier sélectionné dans le navigateur ;
- ne déclenche aucun upload avant soumission ;
- accepte JPEG, PNG et WEBP conformément aux règles backend ;
- remplace visuellement le placeholder ou l'ancienne pochette dans la fiche d'édition.
