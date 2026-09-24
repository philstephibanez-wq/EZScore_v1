# EZScore_v1 — R9.1 Doctrine schema sync

Correction ciblée du `doctrine:schema:validate` qui restait en boucle après R8/R9.

## Cause

Les migrations R8/R9 créaient explicitement des index SQL :

```text
IDX_SONGS_PUBLISHED_AT
IDX_SONG_RATING_SONG
IDX_SONG_RATING_USER
```

mais ces index n'étaient pas déclarés dans le mapping Doctrine des entités.

Résultat : la base contenait des objets que le mapping ne décrivait pas, et Doctrine considérait le schéma comme non synchronisé à chaque validation.

## Correction

R9.1 aligne le mapping ORM avec les index déjà créés par les migrations.

Fichiers modifiés uniquement :

```text
src/Domain/Song/Song.php
src/Domain/Song/SongRating.php
readme.md
```

Aucune migration supplémentaire.
Aucun changement de données.
Aucun CSS/Twig/JS.
Aucune modification fonctionnelle.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_DOCTRINE_SCHEMA_SYNC_R9_1.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

Résultat attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et `doctrine:schema:update --dump-sql` ne doit proposer aucune modification.

## Important

Ne pas exécuter :

```text
doctrine:schema:update --force
```

Si `--dump-sql` affiche encore une requête après R9.1, conserver la sortie exacte : elle identifiera le dernier écart sans modifier la base.
