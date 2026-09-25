# EZScore_v1 — R21 Répertoire Admin / Profil / aperçu pochette

Prérequis : R20.1 installé.

## Contenu

R21 traite les quatre points demandés.

### 1. Retour au statut Importée

Le workspace propose maintenant :

```text
Remettre en Importée
```

pour une personne autorisée à éditer le morceau.

Le back-office Répertoire permet également à l'Admin de choisir directement `Importée`.

`markImported()` remet `published_at` à `NULL`.

Le statut `Analysée` reste réservé à la chaîne d'analyse : il peut être conservé s'il existe déjà, mais ne peut pas être créé manuellement.

### 2. Profil personnel

`Profil` est retiré de la navigation métier principale.

La carte de l'utilisateur connecté en bas du menu devient le lien vers son propre Profil.

Cela vaut pour Reader, Editor et Admin.

La section `Administration` ne mélange donc plus les fonctions personnelles et les fonctions de back-office.

### 3. Aperçu de pochette avant import

Le sélecteur de pochette affiche immédiatement l'image sélectionnée dans la fiche du morceau.

Fonctionne sur :

- Import ;
- Édition.

L'aperçu est entièrement local via `URL.createObjectURL()` : aucun upload avant validation du formulaire.

### 4. Répertoire Admin = back-office chansons

Pour l'Admin, Répertoire ajoute :

- recherche titre / interprète / auteur / compositeur / éditeur ;
- filtre par statut ;
- filtre par éditeur ;
- tri titre / interprète ;
- index A-Z ;
- pagination serveur 50 ;
- changement de statut inline ;
- réattribution à un Éditeur actif ;
- suppression avec confirmation ;
- conservation des filtres et de la page après action.

La modération s'appuie volontairement sur le workflow existant :

```text
Imported -> Analyzed -> Editing -> Published
```

Aucun nouvel état de modération parallèle n'est ajouté.

## Schéma Doctrine

Aucun changement de schéma.

Aucune migration R21.

## Cahier des charges

Mis à jour :

`docs/CAHIER_DES_CHARGES.md`

## Recette

Mis à jour :

`recette.md`

Recette ciblée :

`docs/RECETTE_ADMIN_CATALOG_R21.md`

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_ADMIN_CATALOG_COVER_PROFILE_R21.zip" -C H:\EZScore_v1

php bin\console lint:yaml config translations
php bin\console lint:twig templates

Get-ChildItem src,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php bin\console cache:clear

php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\admin_catalog_r21_contract.php
```

Attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et aucun SQL avec :

```powershell
php bin\console doctrine:schema:update --dump-sql
```

Ne pas utiliser `doctrine:schema:update --force`.

## Recette rapide

1. Répertoire Admin : filtrer `En édition`.
2. Passer un morceau à `Importée`.
3. Réattribuer un morceau à un autre Éditeur.
4. Tester recherche + statut + éditeur + A-Z + pagination.
5. Supprimer un morceau de test.
6. Workspace : tester `Remettre en Importée`.
7. Import : sélectionner une pochette et vérifier son aperçu immédiat.
8. Menu : vérifier que Profil n'est plus une rubrique principale et que la carte utilisateur du bas ouvre le Profil.
