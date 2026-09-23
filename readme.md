# EZScore_v1 — UI foundation R1

## 1. Objectif de cette branche / livraison

`EZScore_v1` repart sur une base propre et modulaire.

La première phase sert à **valider l'UI et le shell applicatif avant de rapatrier les moteurs musicaux de l'ancien EZScore**.

Le principe est strict :

- l'UI musicale est actuellement **mockée** ;
- l'authentification, les utilisateurs, les rôles, les groupes et les playlists utilisent déjà une vraie persistance SQLite ;
- la présentation est définie dans les templates `.score` et leurs fichiers CSS / JavaScript associés ;
- Python orchestre les données, les permissions, les services et la persistance ;
- aucune logique Whisper, STEM, accords, MIDI, Demucs, BS-RoFormer, madmom ou librosa n'est importée dans cette première base.

Quand l'UI sera validée, les providers mock seront remplacés progressivement par les moteurs réels, sans reconstruire les écrans.

---

## 2. Environnement Python imposé

EZScore_v1 utilise **Python 3.13** dans le virtualenv local :

```text
H:\EZScore_v1\.venv-py313
```

Ne pas utiliser le Python système ni l'environnement de l'ancien EZScore.

### Création de l'environnement

PowerShell :

```powershell
cd H:\EZScore_v1
py -3.13 -m venv .venv-py313
.\.venv-py313\Scripts\python.exe -m pip install --upgrade pip setuptools wheel
.\.venv-py313\Scripts\python.exe -m pip install -r requirements.txt
```

### Lancement

```powershell
cd H:\EZScore_v1
.\.venv-py313\Scripts\python.exe -m streamlit run EZScore.py `
  --server.address 127.0.0.1 `
  --server.port 8501 `
  --server.headless true
```

URL locale :

```text
http://127.0.0.1:8501
```

---

## 3. requirements.txt

La première phase garde volontairement un environnement réduit :

```text
streamlit>=1.63,<2
bcrypt>=4.2
jinja2>=3.1
authlib>=1.6
httpx>=0.28
```

Il ne faut pas ajouter maintenant les dépendances audio lourdes de l'ancien projet.

En particulier, cette phase ne nécessite pas :

- torch / torchaudio ;
- Whisper ;
- librosa ;
- Demucs ;
- BS-RoFormer ;
- madmom ;
- lv-chordia ;
- MIDI / soundfont.

Elles seront réintroduites module par module lorsque l'UI cible sera figée.

---

## 4. Architecture modulaire

```text
H:\EZScore_v1
│
├─ EZScore.py
├─ EZScoreTemplate.py
├─ requirements.txt
├─ readme.md
├─ .gitignore
│
├─ .streamlit/
│  └─ secrets.toml.example
│
├─ data/
│  └─ ezscore_v1.db                 # créé au premier lancement
│
├─ ezscore/
│  ├─ core/
│  │  ├─ config.py
│  │  ├─ permissions.py
│  │  └─ types.py
│  │
│  ├─ auth/
│  │  ├─ repository.py
│  │  ├─ service.py
│  │  ├─ local.py
│  │  ├─ google.py
│  │  └─ session.py
│  │
│  ├─ users/
│  │  ├─ models.py
│  │  ├─ repository.py
│  │  └─ service.py
│  │
│  ├─ groups/
│  │  ├─ models.py
│  │  ├─ repository.py
│  │  └─ service.py
│  │
│  ├─ playlists/
│  │  ├─ models.py
│  │  ├─ repository.py
│  │  └─ service.py
│  │
│  ├─ songs/
│  │  ├─ models.py
│  │  ├─ repository.py
│  │  └─ service.py
│  │
│  ├─ persistence/
│  │  ├─ db.py
│  │  ├─ migrations.py
│  │  └─ schema.py
│  │
│  ├─ mock/
│  │  ├─ songs.py
│  │  ├─ stems.py
│  │  └─ timeline.py
│  │
│  └─ ui/
│     ├─ app.py
│     ├─ shell.py
│     ├─ routing.py
│     ├─ pages/
│     │  ├─ first_run.py
│     │  ├─ login.py
│     │  ├─ catalog.py
│     │  ├─ song_workspace.py
│     │  ├─ playlists.py
│     │  ├─ admin_users.py
│     │  └─ admin_groups.py
│     └─ viewmodels/
│        └─ song_workspace.py
│
└─ templates/
   ├─ EZScore.score
   ├─ shell/
   │  ├─ app.score
   │  └─ sidebar.score
   ├─ auth/
   │  ├─ login.score
   │  └─ first-run.score
   ├─ admin/
   │  ├─ users.score
   │  └─ groups.score
   ├─ playlists/
   │  └─ list.score
   └─ song/
      ├─ workspace.score
      ├─ workspace.css
      └─ workspace.js
```

---

## 5. Règles d'architecture

### Présentation

Toute présentation structurante doit rester dans les templates SCORE :

```text
Template SCORE
      ↑
ViewModel
      ↑
Service
      ↑
Repository
      ↑
SQLite / Google / futur moteur musical
```

Règles :

1. Les pages UI n'accèdent pas directement à SQLite.
2. Les templates ne déterminent jamais les droits d'accès.
3. Les services métier ne connaissent pas Streamlit.
4. Les repositories ne connaissent pas l'UI.
5. Les providers `mock/` sont temporaires et uniquement musicaux.
6. Pas de fichier monolithique de plusieurs milliers de lignes.
7. Pas de duplication entre playlists personnelles et playlists de groupe.
8. Pas de logique admin dispersée dans les templates.
9. Pas de HTML/CSS/JS musical reconstruit dynamiquement dans un moteur d'analyse Python.
10. Les données musicales et leur représentation restent deux responsabilités séparées.

---

## 6. First run

Au premier lancement, si la table `users` est vide, EZScore_v1 affiche uniquement la page :

```text
Création de l'administrateur
```

Le formulaire demande :

- nom affiché ;
- e-mail ;
- mot de passe ;
- confirmation du mot de passe.

Le premier utilisateur reçoit automatiquement le rôle :

```text
admin
```

Le mot de passe local est hashé avec `bcrypt`.

Le first-run ne doit plus être accessible après création du premier utilisateur.

---

## 7. Authentification locale

La page de connexion réelle accepte :

- e-mail ;
- mot de passe local.

Les sessions applicatives utilisent `st.session_state` et un identifiant utilisateur interne.

Les rôles disponibles sont :

```text
admin
editor
reader
```

La déconnexion est disponible depuis la barre latérale.

---

## 8. Google SSO

Google SSO est prévu dès cette première livraison via l'authentification OIDC de Streamlit.

Ne jamais stocker le Client ID ou le Client Secret dans Git.

Copier :

```text
.streamlit/secrets.toml.example
```

vers :

```text
.streamlit/secrets.toml
```

puis configurer :

```toml
[auth]
redirect_uri = "http://localhost:8501/oauth2callback"
cookie_secret = "UNE_CLE_ALEATOIRE_LONGUE"

[auth.google]
client_id = "GOOGLE_CLIENT_ID"
client_secret = "GOOGLE_CLIENT_SECRET"
server_metadata_url = "https://accounts.google.com/.well-known/openid-configuration"
```

Dans Google Cloud Console, l'URI de redirection locale doit correspondre à :

```text
http://localhost:8501/oauth2callback
```

### Politique d'association

Après le first-run, un login Google **ne crée pas automatiquement un utilisateur arbitraire**.

Le compte doit déjà exister dans EZScore avec la même adresse e-mail. Au premier login Google réussi :

- l'identité Google est associée au compte EZScore existant ;
- le `sub` Google est mémorisé ;
- l'avatar Google est récupéré s'il est disponible.

Cette règle évite qu'un utilisateur externe se crée lui-même un compte éditeur ou lecteur sans décision de l'administrateur.

---

## 9. Rôles et affichage

### Admin

Accès à :

- répertoire ;
- workspace musical mock ;
- playlists personnelles ;
- playlists de groupe ;
- administration des utilisateurs ;
- administration des groupes ;
- affectation des rôles ;
- activation / désactivation des comptes ;
- future affectation des éditeurs aux morceaux.

### Editor

Accès à :

- répertoire ;
- workspace musical ;
- édition des éléments musicaux lorsque les moteurs réels seront reconnectés ;
- playlists personnelles ;
- playlists de groupe selon permissions.

### Reader

Accès à :

- répertoire autorisé ;
- affichage musical en lecture seule ;
- playlists personnelles ;
- playlists de groupe visibles.

Le rôle n'est jamais décidé dans JavaScript. Les permissions sont produites côté Python par `core/permissions.py` puis transmises au ViewModel.

Permissions prévues :

```text
can_admin_users
can_manage_group
can_assign_editor
can_edit_song
can_publish
can_use_step2
can_manage_personal_playlist
can_manage_group_playlist
readonly
```

---

## 10. Groupes

Le modèle réel contient :

```text
groups
group_members
song_group_access
```

Un membre peut avoir dans un groupe le rôle :

```text
owner
manager
member
```

Cette notion est distincte du rôle global EZScore `admin/editor/reader`.

La première UI permet déjà :

- création d'un groupe ;
- affichage de ses membres ;
- ajout / mise à jour d'un membre ;
- choix du rôle dans le groupe.

---

## 11. Playlists

Il n'existe qu'un seul modèle de playlist :

```text
playlists
playlist_items
```

Une playlist possède :

```text
owner_type = "user" | "group"
owner_id
name
description
is_public
created_by
```

Cette conception évite deux implémentations différentes.

### Playlist personnelle

```text
owner_type = user
owner_id = user connecté
```

### Playlist de groupe

```text
owner_type = group
owner_id = id du groupe
```

Le réordonnancement et l'ajout des morceaux seront ajoutés lorsque l'UI playlist définitive sera validée.

---

## 12. UI musicale mock

L'écran `Analyse mock` ne réalise actuellement aucune analyse audio.

Il sert à figer le contrat visuel avant de reconnecter les moteurs.

Il comprend déjà :

- `1 · STEMS` ;
- `2 · PAROLES` ;
- cartouche chanson ;
- titre ;
- auteur / interprète ;
- éditeur ;
- time signature ;
- capo ;
- strumming ;
- mixeur STEM ;
- player ;
- waveform mock ;
- diagramme mock ;
- conducteur harmonique ;
- paroles ;
- bloc d'édition caché pour le lecteur.

### Contrat de conducteur

Le modèle visuel validé à poursuivre est :

```text
1 case = 1 beat
```

En 4/4 :

```text
Mesure #12
[Cm | - | - | -]
```

Le beat courant est le seul beat surligné.

Si l'accord continue à la mesure suivante, la nouvelle mesure répète l'accord sur son premier beat :

```text
#12               #13
[Cm|-|-|-]         [Cm|-|-|-]
```

Plusieurs accords peuvent exister dans une même mesure :

```text
4/4 : [Em | - | G | F]
2/4 : [Em | G]
```

Les mots restent sur **la même ligne de vers**. Le mot courant est uniquement mis en évidence ; il ne doit pas monter ou descendre vers la mire.

La mire sert à aligner visuellement :

- le diagramme courant ;
- le beat courant ;
- le mot courant dans sa ligne de paroles.

---

## 13. Ce qui est volontairement absent de R1

Ne pas réintroduire avant validation de l'UI :

- extraction STEM ;
- BS-RoFormer ;
- Demucs ;
- Whisper ;
- analyse des accords ;
- détection automatique de signature ;
- MIDI ;
- player audio réel ;
- synchronisation audio ;
- publication ;
- historique de versions musicales ;
- anciennes monkey-patches de l'ancien EZScore.

Le but de R1 est de valider **la structure applicative et la représentation**, pas la qualité de l'analyse musicale.

---

## 14. Migration future des moteurs

La migration se fera par provider, sans modifier le contrat UI :

```text
SongWorkspaceViewModel
        ↑
MusicProvider
   ↙          ↘
Mock          Real
R1            plus tard
```

Ordre recommandé après validation UI :

1. source audio + durée ;
2. player réel ;
3. STEM et mixeur temps réel ;
4. timeline beats / mesures ;
5. accords ;
6. paroles / timestamps ;
7. blocs / structure ;
8. édition et persistance musicale ;
9. publication.

À chaque étape, le provider mock correspondant est remplacé par la logique réelle.

---

## 15. Base de données

Fichier local :

```text
data/ezscore_v1.db
```

Créé automatiquement au premier lancement.

Tables R1 :

```text
users
groups
group_members
songs
song_group_access
playlists
playlist_items
```

Le fichier SQLite est exclu de Git par `.gitignore`.

---

## 16. Git / fichiers à ne jamais pousser

Le `.gitignore` exclut notamment :

```text
.venv-py313/
__pycache__/
*.pyc
.streamlit/secrets.toml
data/*.db
```

Le fichier :

```text
.streamlit/secrets.toml.example
```

est au contraire prévu pour être versionné, car il ne contient aucun secret réel.

---

## 17. Définition de la première phase

La phase UI sera considérée comme terminée lorsque les écrans auront été validés pour les trois profils :

```text
Admin
Editor
Reader
```

et notamment :

- first run ;
- login local ;
- Google SSO ;
- sidebar ;
- répertoire ;
- groupes ;
- playlists personnelles ;
- playlists groupe ;
- formulaire chanson ;
- Step 1 STEMS ;
- Step 2 PAROLES ;
- mixeur ;
- player ;
- conducteur ;
- éditeur visuel ;
- vues readonly lecteur.

Ensuite seulement commence le rapatriement de la logique musicale.

---

## 18. Version de cette livraison

```text
EZScore_v1 UI FOUNDATION R1
```

Objectif : fournir une base propre, testable et modulaire, sans dépendance sur l'architecture historique du moteur musical.
