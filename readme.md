# EZScore_v1 — Symfony + Python foundation R3

## 1. Décision d'architecture

EZScore_v1 abandonne Streamlit pour l'application web.

Le découpage cible est désormais :

```text
Symfony / Twig
    UI, login, Google SSO, utilisateurs, rôles,
    groupes, playlists, catalogue, backoffice
        |
        +-- jQuery / JavaScript / WebAudio
        |      interactions navigateur, player, mixeur
        |
        +-- HTTP / JSON --> Python / FastAPI
                              Torch, STEMS, Whisper,
                              accords, rythme, MIDI
```

Règle : Symfony et Python ne s'importent jamais mutuellement. Ils communiquent uniquement par API HTTP/JSON.

## 2. Ce qui a été purgé

L'ancien socle UI Python/Streamlit n'est plus utilisé :

```text
EZScore.py
EZScoreTemplate.py
requirements.txt                   # ancien requirements racine Streamlit
ezscore/                            # ancien shell Python métier/UI
.streamlit/
templates/*.score                  # ancien moteur SCORE
templates/song/*.score
```

La présentation Symfony utilise désormais Twig (`templates/*.html.twig`).

Le dossier `data/` est conservé. Ne pas supprimer une base locale existante sans sauvegarde explicite.

## 3. Arborescence cible

```text
H:\EZScore_v1
|
|-- composer.json
|-- .env.example
|-- readme.md
|
|-- public/
|   `-- index.php
|
|-- src/
|   `-- Controller/
|
|-- config/
|   |-- bundles.php
|   |-- routes.yaml
|   |-- services.yaml
|   `-- packages/
|
|-- templates/                     # Twig
|-- assets/
|   |-- css/
|   `-- js/                        # jQuery / JS / futur WebAudio
|
|-- analysis/
|   |-- requirements.txt
|   `-- app/
|       `-- main.py                # FastAPI
|
|-- data/
`-- var/
```

Les domaines Symfony seront ajoutés par modules : `Auth`, `User`, `Group`, `Playlist`, `Song`, puis `Publication`.

## 4. Prérequis Symfony

- PHP >= 8.2
- Composer 2
- SQLite pour le développement initial

Vérification :

```powershell
php -v
composer --version
```

Installation :

```powershell
cd H:\EZScore_v1
Copy-Item .env.example .env.local
composer install
```

Serveur local Symfony, si Symfony CLI est installé :

```powershell
symfony server:start
```

Sinon, pour le squelette actuel :

```powershell
php -S 127.0.0.1:8000 -t public
```

URL :

```text
http://127.0.0.1:8000
```

## 5. Python 3.13 imposé pour l'analyse

Le moteur musical reste en Python 3.13 dans :

```text
H:\EZScore_v1\.venv-py313
```

Création :

```powershell
cd H:\EZScore_v1
py -3.13 -m venv .venv-py313
.\.venv-py313\Scripts\python.exe -m pip install --upgrade pip setuptools wheel
.\.venv-py313\Scripts\python.exe -m pip install -r analysis\requirements.txt
```

Lancement du service d'analyse :

```powershell
.\.venv-py313\Scripts\python.exe -m uvicorn analysis.app.main:app --host 127.0.0.1 --port 8502
```

Test :

```text
http://127.0.0.1:8502/health
```

R3 n'installe volontairement encore ni Torch, ni Whisper, ni BS-RoFormer, ni librosa. Ils seront ajoutés au `analysis/requirements.txt` lorsque les moteurs seront rapatriés.

## 6. Authentification et backoffice

Ils seront implémentés côté Symfony, pas côté Python :

- first-run : création du premier Admin ;
- login local ;
- Google OAuth/OIDC ;
- rôles globaux `admin`, `editor`, `reader` ;
- gestion des utilisateurs ;
- groupes et membres ;
- playlists personnelles ;
- playlists de groupe ;
- affectation des éditeurs ;
- droits calculés côté serveur.

Les dépendances OAuth Google sont déjà déclarées dans `composer.json`.

## 7. Données métier

Symfony sera propriétaire de la base métier :

```text
users
groups
group_members
songs
song_assignments
song_group_access
playlists
playlist_items
```

Doctrine + migrations seront utilisés.

Python ne modifie pas directement ces tables. Il retourne des résultats d'analyse structurés à Symfony via l'API.

## 8. Jobs d'analyse

Les traitements longs ne doivent pas bloquer une requête PHP.

Contrat futur :

```text
POST /jobs/stems
POST /jobs/chords
POST /jobs/lyrics
GET  /jobs/{id}
GET  /jobs/{id}/result
```

Symfony crée/demande le job, le navigateur suit la progression, Python produit le résultat.

## 9. Règles de modularité

1. Symfony = application et métier web.
2. Python = calcul musical uniquement.
3. JavaScript/WebAudio = temps réel navigateur.
4. Aucun import croisé Symfony/Python.
5. Les traitements longs passent par des jobs.
6. Les contrôleurs Symfony restent fins.
7. La logique métier vit dans des services/domaines dédiés.
8. Twig reste présentation, pas autorisation.
9. Les permissions sont vérifiées côté serveur.
10. Les secrets vivent dans `.env.local`, jamais dans Git.

## 10. Purge de l'ancienne R1/R2 sur la machine locale

Cette livraison est un remplacement de fondation. Pour ne pas laisser les anciens fichiers Streamlit dans le dépôt, supprimer uniquement les éléments obsolètes avant d'extraire R3 :

```powershell
cd H:\EZScore_v1

Remove-Item .\EZScore.py -Force -ErrorAction SilentlyContinue
Remove-Item .\EZScoreTemplate.py -Force -ErrorAction SilentlyContinue
Remove-Item .\requirements.txt -Force -ErrorAction SilentlyContinue
Remove-Item .\.streamlit -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item .\ezscore -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item .\templates -Recurse -Force -ErrorAction SilentlyContinue
```

Ne pas supprimer `.git`, `data` ou `.venv-py313`.

Extraire ensuite l'archive R3 à la racine de `H:\EZScore_v1`.

## 11. Étape suivante

Avant tout rapatriement musical : construire et valider en Symfony les écrans réels de l'application :

1. first-run Admin ;
2. login + Google SSO ;
3. shell/navigation ;
4. utilisateurs ;
5. groupes ;
6. playlists ;
7. catalogue ;
8. workspace chanson mock ;
9. vues Admin / Editor / Reader.

Ensuite seulement, les providers musicaux Python seront reconnectés progressivement.
