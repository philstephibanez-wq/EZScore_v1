# EZScore_v1 — architecture de référence

## R6 — i18n, DATA source de vérité, jobs d'analyse non bloquants

R6 formalise les contraintes d'architecture qui deviennent obligatoires pour toute la suite du projet.

## 1. Contraintes non négociables

### 1.1 Une seule source de vérité : les DATA

Les données persistées sont la seule source de vérité de l'application.

Conséquences :

- aucun morceau de démonstration codé en dur dans un provider ;
- aucun accord, parole, bloc, STEM, mesure, éditeur ou état d'analyse inventé par l'UI ;
- aucun état métier maintenu uniquement dans JavaScript ;
- aucun résultat calculé n'est considéré valide tant qu'il n'est pas persisté par le propriétaire de la donnée ;
- les templates ne reconstruisent pas de logique métier ;
- un écran sans donnée affiche explicitement l'absence de donnée.

Le `MockSongProvider` historique n'est plus utilisé et doit être supprimé.

### 1.2 Aucun fallback

EZScore_v1 n'utilise pas de fallback silencieux pour masquer une erreur ou une donnée absente.

Interdit :

- substituer une analyse simplifiée si le moteur demandé échoue ;
- basculer CPU/GPU silencieusement ;
- fabriquer un résultat mock quand la donnée n'existe pas ;
- choisir une autre locale si une locale persistée est invalide ;
- remplacer un provider indisponible par une implémentation de secours non demandée ;
- masquer une incompatibilité par une valeur par défaut métier.

Une erreur doit être visible, typée et traçable. Une donnée absente reste absente.

Les valeurs par défaut explicitement définies par le produit (par exemple locale initiale `fr`) ne sont pas des fallbacks.

### 1.3 Séparation stricte des responsabilités

```text
Browser
  HTML / CSS / jQuery / JavaScript / WebAudio
       |
       v
Symfony
  application / sécurité / droits / données métier / orchestration
       |
       +---- HTTP/JSON ----> Python API / workers
                              calcul musical uniquement
```

Règles :

- Symfony ne charge aucun module Python ;
- Python ne charge aucun code Symfony/PHP ;
- Twig n'accède pas directement à la base ;
- Twig n'autorise rien : les permissions sont décidées côté serveur ;
- JavaScript n'est jamais la source d'une permission ou d'un état métier ;
- les contrôleurs Symfony orchestrent mais ne portent pas les algorithmes métier ;
- Doctrine/repositories portent la persistance ;
- les services/domaines portent les règles métier ;
- Python ne devient pas propriétaire des tables métier Symfony.

### 1.4 Une analyse neuronale ne bloque jamais les utilisateurs en ligne

Aucun traitement lourd (Torch, Whisper, BS-RoFormer, séparation STEMS, analyse d'accords neuronale, etc.) n'est exécuté dans une requête HTTP Symfony bloquante.

Cycle obligatoire :

```text
Utilisateur
   |
   v
Symfony crée un AnalysisJob persistant
   status = queued
   |
   v
dispatcher / queue durable
   |
   v
worker Python séparé
   |
   v
running -> completed / failed / cancelled
   |
   v
Symfony persiste l'état et le résultat canonique
```

Le navigateur consulte l'état du job sans immobiliser la session.

États normalisés :

```text
queued
running
completed
failed
cancelled
```

Le nombre de workers GPU est limité indépendamment du nombre d'utilisateurs connectés. Avec une RTX 2060 6 Go, plusieurs demandes peuvent être mises en file sans lancer plusieurs gros modèles simultanément.

R6 crée le modèle persistant `analysis_jobs`, mais **ne simule pas encore de queue**. Le raccordement au vrai transport/worker sera un lot dédié. C'est volontaire : pas de faux asynchronisme.

## 2. i18n obligatoire FR / EN

Langues supportées dès R6 :

```text
fr
en
```

- locale initiale : `fr`;
- préférence persistée dans `users.locale`;
- session utilisée uniquement avant authentification ;
- changement de langue explicite via l'UI ;
- catalogues complets :
  - `translations/messages.fr.yaml`
  - `translations/messages.en.yaml`
- pas de fallback de traduction configuré ;
- une locale persistée hors `fr/en` provoque une erreur explicite.

Les nouveaux textes d'interface doivent être ajoutés aux deux catalogues dans le même changement.

## 3. Répertoire : fin du mock comme source

R6 introduit `App\Domain\Song\Song`.

Le répertoire lit exclusivement la table `songs`.

Si la table est vide :

```text
Aucun morceau dans les données EZScore.
```

Il n'y a plus de Susanna / La Bohème / Je te donne injectés par le code.

Admin et Éditeur peuvent créer un morceau réel dans la base. Un éditeur créé par un compte `ROLE_EDITOR` lui est affecté. Un admin peut affecter un éditeur existant.

Le workspace n'invente plus de waveform, accord, mesure ou parole. Tant que l'analyse n'existe pas dans les données, il affiche explicitement qu'aucune donnée d'analyse n'est disponible.

## 4. Nouvelles données R6

Migration :

```text
DoctrineMigrations\Version20260924020000
```

Ajouts :

```text
users.locale
songs
analysis_jobs
```

`analysis_jobs` est la source canonique de l'état d'une analyse côté application.

## 5. Installation R6

Le ZIP ne contient que les fichiers nouveaux/modifiés et aucun script de patch.

Depuis :

```powershell
cd H:\EZScore_v1
```

Extraire :

```powershell
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R6_I18N_DATA_ASYNC.zip" -C H:\EZScore_v1
```

Supprimer l'ancien provider mock devenu interdit :

```powershell
Remove-Item .\src\Domain\Song\MockSongProvider.php -Force -ErrorAction SilentlyContinue
```

Installer Symfony Translation et mettre à jour le lock Composer :

```powershell
composer update symfony/translation --with-all-dependencies
```

Puis :

```powershell
composer dump-autoload
php bin\console cache:clear
php bin\console doctrine:migrations:migrate --no-interaction
php bin\console doctrine:mapping:info
php bin\console doctrine:schema:validate
```

Résultat attendu du dernier contrôle :

```text
Mapping
-------
[OK] The mapping files are correct.

Database
--------
[OK] The database schema is in sync with the mapping files.
```

## 6. Vérifications i18n

Lancer :

```powershell
php bin\console debug:translation fr
php bin\console debug:translation en
```

Les deux catalogues doivent être chargés.

## 7. Lancement local

```powershell
php -S 127.0.0.1:8000 -t public public/index.php
```

Ouvrir :

```text
http://127.0.0.1:8000
```

## 8. Python

Python reste en 3.13 :

```text
H:\EZScore_v1\.venv-py313
```

FastAPI n'est pas encore autorisé à exécuter une analyse lourde depuis le thread/processus HTTP.

Le futur service devra avoir :

```text
API légère -> file durable -> workers Python séparés
```

et non :

```text
requête HTTP -> Whisper/Torch directement -> attente utilisateur
```

## 9. Règle de livraison

Pour tout lot futur :

- fichier complet de remplacement, pas de patch dynamique ;
- ZIP sans dossier racine parasite ;
- `readme.md` obligatoire ;
- aucune modification silencieuse de DATA ;
- aucune régression vers mock/fallback ;
- aucune logique métier dupliquée entre Symfony, JavaScript et Python.
