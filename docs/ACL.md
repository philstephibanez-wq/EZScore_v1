# EZScore ACL — architecture native Symfony

## Objectif

Le modèle d'autorisation reprend les concepts utiles de l'ACL OPUS :

- rôle ;
- ressource ;
- privilège ;
- condition ;
- refus par défaut.

Il n'embarque pas le moteur ACL historique OPUS. Symfony Security reste l'unique moteur de décision.

## Correspondance OPUS -> EZScore/Symfony

| OPUS | EZScore |
| --- | --- |
| Role | `ROLE_READER`, `ROLE_EDITOR`, `ROLE_ADMIN` + rôles contextuels de groupe |
| Resource | objets Doctrine `Song`, `Playlist`, `UserGroup` |
| Privilege | constantes `AclPrivilege::*` |
| Conditions | logique de `Voter` + état Doctrine |
| `isAllowed()` | `is_granted()` / `denyAccessUnlessGranted()` |
| héritage de rôles | `security.role_hierarchy` |
| deny by default | comportement des Voters Symfony |

## Règle d'architecture

Les contrôleurs et templates ne doivent pas reconstruire les droits métier avec des `if` locaux.

Ils demandent une décision :

```php
$this->denyAccessUnlessGranted(AclPrivilege::PLAYLIST_EDIT, $playlist);
```

ou :

```twig
{% if is_granted('PLAYLIST_EDIT', playlist) %}
```

Les conditions sont centralisées dans :

- `PlaylistVoter`
- `GroupVoter`
- `SongVoter`

## Rôles globaux

```text
ROLE_READER
ROLE_EDITOR -> ROLE_READER
ROLE_ADMIN  -> ROLE_EDITOR -> ROLE_READER
```

## Règles Lecteur

Un Lecteur :

- peut créer une playlist personnelle ;
- peut gérer ses propres playlists personnelles ;
- ne peut pas créer un groupe ;
- ne peut pas administrer un groupe ;
- peut inviter un autre utilisateur déjà inscrit à sa playlist ;
- l'invité ne voit la playlist qu'après acceptation ;
- l'accès invité est lecture seule ;
- une playlist partagée ne contourne jamais les droits sur une chanson.

## Règles Groupe

La création de groupe est réservée à :

```text
ROLE_EDITOR
ROLE_ADMIN
```

Dans un groupe :

- `member` : vue ;
- `manager` : édition + gestion des membres ;
- `owner` : droits manager + suppression + délégation owner ;
- `ROLE_ADMIN` : tous droits.

L'invariant "au moins un owner" reste une règle domaine appliquée par le contrôleur lors d'un changement de membership.

## Extension future

Les délégations fines prévues pour les groupes doivent être ajoutées comme données métier, puis consommées par `GroupVoter`.

Exemple futur :

```text
can_manage_members
can_manage_playlists
can_delegate
```

Aucun contrôleur ne devra être réécrit : seule la condition du Voter évoluera.
