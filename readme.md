# EZScore_v1 — architecture, droits fonctionnels et pont d’analyse

Ce document fixe les règles fonctionnelles validées avant la réintégration des analyseurs Python.

---

## 1. Principe général

EZScore distingue clairement :

- les **droits globaux** du compte (`USER`, `EDITOR`, `ADMIN`) ;
- les **droits locaux** dans un groupe ;
- la **propriété d’une chanson** ;
- la **propriété d’une playlist** ;
- l’**appartenance à un groupe** ;
- les **délégations de pouvoir** accordées dans un groupe.

Une délégation locale ne transforme jamais automatiquement un utilisateur en `EDITOR` ou `ADMIN` global.

---

## 2. Répertoire, MP3, karaoké, import et édition

| Profil | Répertoire | MP3 | Karaoké / synchro paroles-accords | Import | Édition |
|---|---|---|---|---|---|
| **Public non connecté** | chansons publiées | **non** | **démo limitée à 20 s** | non | non |
| **Utilisateur enregistré / connecté** | chansons publiées | **oui** | **oui** | non | non |
| **Éditeur** | **ses chansons + chansons publiées** | **oui** | **oui sur les siennes** | **oui** | **uniquement ses chansons** |
| **Admin** | **toutes les chansons** | **oui** | **oui** | **oui** | **toutes les chansons** |

### 2.1 Public non connecté

Le public non connecté :

- peut consulter le répertoire des chansons publiées ;
- ne peut pas écouter le MP3 complet ;
- peut utiliser une **prévisualisation karaoké limitée**, par exemple à **20 secondes** ;
- ne peut pas importer ;
- ne peut pas éditer.

La limite de karaoké public doit être appliquée côté serveur et ne doit pas être un simple masquage de l’interface.

Configuration prévue :

```text
PUBLIC_KARAOKE_PREVIEW_SECONDS = 20
```

L’API publique ne doit pas exposer l’intégralité des données de synchronisation si l’utilisateur n’a droit qu’à la prévisualisation.

### 2.2 Utilisateur enregistré / connecté

Un utilisateur connecté :

- voit les chansons publiées ;
- peut écouter le MP3 ;
- peut utiliser le karaoké complet des chansons publiées ;
- ne peut pas importer de nouvelle chanson ;
- ne peut pas éditer une chanson par son seul statut de `USER`.

### 2.3 Éditeur

Un éditeur :

- voit ses propres chansons, publiées ou non ;
- voit également toutes les chansons publiées ;
- peut écouter les chansons visibles dans son répertoire ;
- peut utiliser le karaoké complet sur **ses propres chansons** ;
- peut importer de nouvelles chansons ;
- ne peut modifier que **ses propres chansons**, sauf délégation locale explicitement accordée selon les règles de groupe.

Un éditeur ne voit donc pas les brouillons/non publiés des autres éditeurs.

### 2.4 Administrateur global

Un `ADMIN` a tous les droits sur toutes les ressources :

- toutes les chansons ;
- tous les MP3 ;
- tous les karaokés ;
- tous les imports ;
- toutes les éditions ;
- tous les groupes ;
- toutes les playlists ;
- toutes les délégations.

---

## 3. Création d’un morceau

La création manuelle d’un morceau n’existe pas.

Le bloc de type :

```text
Nouveau morceau
Titre
Auteur / interprète
Éditeur
Commentaire
Créer le morceau
```

doit disparaître.

L’entrée d’une nouvelle chanson dans EZScore commence uniquement par un **import** :

```text
Importer
   ↓
fichier audio
   ↓
création de l’entrée chanson
   ↓
analyse / métadonnées / édition
   ↓
publication
```

L’import est réservé aux `EDITOR` et `ADMIN`.

---

## 4. Publication

Une chanson possède un état de publication.

Le modèle privilégié est :

```text
publishedAt = NULL
```

pour une chanson non publiée, et une date pour une chanson publiée.

Conséquences :

- public et `USER` : uniquement chansons publiées ;
- `EDITOR` : ses chansons + chansons publiées ;
- `ADMIN` : toutes les chansons.

La publication contrôle la visibilité générale.

La propriété éditoriale contrôle qui peut modifier la chanson.

---

## 5. Groupes et playlists

### 5.1 Droits de base

| Profil | Groupes | Playlists |
|---|---|---|
| **Public non connecté** | non | non |
| **Utilisateur enregistré / connecté** | **création + gestion des siens** | **création + gestion des siennes** |
| **Éditeur** | **création + gestion des siens** | **création + gestion des siennes** |
| **Admin** | **tous droits sur tous les groupes** | **tous droits sur toutes les playlists** |

Le rôle `EDITOR` n’apporte pas, à lui seul, de privilège particulier supplémentaire sur les groupes ou playlists.

---

## 6. Groupe d’utilisateurs

Un groupe est composé uniquement d’utilisateurs enregistrés.

Le créateur du groupe devient automatiquement son **administrateur de groupe**.

Un groupe possède :

```text
Groupe
  administrateur(s)
  membres
  playlists du groupe
```

Le créateur/admin du groupe peut notamment :

- ajouter un utilisateur au groupe ;
- retirer un utilisateur du groupe ;
- créer une playlist de groupe ;
- supprimer une playlist de groupe ;
- renommer ou gérer une playlist de groupe ;
- ajouter une chanson à une playlist ;
- retirer une chanson d’une playlist ;
- déléguer certains de ses pouvoirs à d’autres membres.

---

## 7. Playlists appartenant au groupe

Une playlist de groupe **appartient réellement au groupe**.

Elle ne doit pas être modélisée comme une playlist personnelle simplement partagée.

Exemple :

```text
Groupe "Band A"
  membres :
    Steve
    Marie
    Paul

  playlists du groupe :
    "Répétition septembre"
    "Set concert"
```

Tous les membres du groupe héritent automatiquement de la visibilité des playlists du groupe :

```text
Steve  -> voit les playlists du groupe
Marie  -> voit les playlists du groupe
Paul   -> voit les playlists du groupe
```

Le modèle existant :

```text
playlist.owner_type = user | group
playlist.owner_id   = ...
```

doit être conservé et exploité.

---

## 8. Chansons dans une playlist de groupe

Ajouter une chanson à une playlist de groupe ne transfère pas la propriété de la chanson.

Il faut distinguer :

```text
propriété de la chanson
≠
présence de la chanson dans une playlist de groupe
```

Une playlist peut donc référencer une chanson sans que le groupe en devienne propriétaire.

Le propriétaire/éditeur initial de la chanson reste conservé.

---

## 9. Administration et délégation dans un groupe

Le créateur du groupe est administrateur par défaut.

L’administrateur peut déléguer tout ou partie de ses pouvoirs à d’autres membres du groupe.

Droits locaux envisagés :

| Droit local | Effet |
|---|---|
| **Gérer les membres** | ajouter / retirer des utilisateurs du groupe |
| **Gérer les playlists** | créer / renommer / supprimer les playlists du groupe |
| **Gérer le contenu des playlists** | ajouter / retirer des chansons des playlists |
| **Gérer les délégations** | accorder ou retirer des pouvoirs aux autres membres |
| **Administrer le groupe** | tous les droits précédents |

Exemple :

```text
Groupe "Band A"

Steve
  administrateur
  tous les droits

Marie
  délégation :
    gérer playlists
    gérer contenu playlists

Paul
  délégation :
    gérer membres

Jacques
  membre simple
```

Une délégation est locale au groupe.

Elle ne change pas le rôle global EZScore du membre.

---

## 10. Délégation d’édition

Le modèle général est :

```text
droits globaux EZScore
    USER / EDITOR / ADMIN

droits locaux dans un groupe
    membre
    délégations spécifiques
    administrateur du groupe
```

Une délégation peut permettre à un `USER` d’agir sur une ressource déterminée sans lui donner les droits d’un `EDITOR` global.

Exemples :

```text
ROLE_USER + délégation playlist
    -> peut gérer la playlist concernée

ROLE_USER + délégation de gestion du groupe
    -> peut exercer les pouvoirs reçus dans ce groupe

ROLE_EDITOR
    -> peut importer
    -> édite ses propres chansons
    -> peut également recevoir des délégations locales

ROLE_ADMIN
    -> tous droits
```

La délégation ne doit jamais modifier silencieusement la propriété d’une ressource.

---

## 11. Principe de sécurité

Les autorisations doivent être contrôlées côté serveur.

Ne pas se contenter :

- de masquer un bouton ;
- de désactiver un contrôle JavaScript ;
- de limiter une lecture uniquement côté navigateur.

Le serveur doit contrôler au minimum :

```text
can_list
can_play_audio
can_use_karaoke
can_import
can_edit
can_manage_group
can_manage_playlist
can_manage_playlist_content
can_manage_delegations
```

Les droits effectifs sont calculés à partir :

- du rôle global ;
- de l’identité du propriétaire ;
- de l’état de publication ;
- de l’appartenance au groupe ;
- des délégations locales.

---

# 12. Fondation du pont d’analyse

La réintégration des analyseurs Python reste séparée de ces règles fonctionnelles.

Le pont d’analyse repose sur un contrat versionné :

```text
ezscore.analysis.v1
```

API interne prévue pour le worker Python :

```text
POST /internal/analysis/jobs/claim
GET  /internal/analysis/jobs/{id}
POST /internal/analysis/jobs/{id}/progress
POST /internal/analysis/jobs/{id}/complete
POST /internal/analysis/jobs/{id}/fail
```

Cycle d’un job :

```text
queued
  ↓
running
  ↓
completed
```

ou :

```text
queued / running
  ↓
failed
```

La couche de transport ne fixe volontairement **aucun ordre des analyseurs musicaux**.

Les futurs modules pourront inclure notamment :

```text
stems
paroles
accords
tempo / métrique
sections
phonèmes
MIDI vocal
...
```

Leur orchestration sera décidée ultérieurement selon les dépendances musicales et techniques réelles.

---

## 13. Format de résultat d’analyse

Le contrat conserve une enveloppe générique et modulaire :

```json
{
  "schema_version": "ezscore.analysis.v1",
  "job_id": 12,
  "song_id": 4,
  "kind": "analysis",
  "engine": {
    "name": "worker-name",
    "version": "..."
  },
  "outputs": {
    "module_name": {}
  },
  "artifacts": [],
  "metrics": {},
  "warnings": []
}
```

Les sorties spécifiques restent sous `outputs`.

Le transport ne doit donc pas imposer une pipeline musicale linéaire.

---

## 14. Sécurité du worker Python

Aucun secret ne doit être stocké dans Git.

Le worker utilise un token local :

```powershell
$env:ANALYSIS_WORKER_TOKEN = "REMPLACER_PAR_UN_TOKEN_LONG_ET_ALEATOIRE"
```

Puis le serveur :

```powershell
php -S 127.0.0.1:8501 -t H:\EZScore_v1\public
```

Le worker peut envoyer :

```text
X-EZScore-Analysis-Token: <token>
```

ou :

```text
Authorization: Bearer <token>
```

Sans token configuré, l’API interne d’analyse doit rester indisponible.

---

## 15. Règles d’architecture à conserver

- Symfony possède les données métier et les autorisations.
- Python analyse et produit des résultats.
- Les analyseurs Python restent modulaires.
- Les jobs d’analyse sont asynchrones.
- Une analyse longue ne doit pas bloquer une requête web.
- Les résultats persistés sont la source de vérité.
- L’interface ne doit pas dépendre de l’état mémoire d’un processus Python.
- Les futures évolutions des analyseurs ne doivent pas imposer de régression au shell Symfony.
