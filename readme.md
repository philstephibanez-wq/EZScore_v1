# EZScore_v1 — R32.2 Doctrine schema default sync

Après la migration R32, Doctrine proposait encore de reconstruire entièrement la table `songs`.

La cause est ciblée :

```sql
chord_analysis_level VARCHAR(16) NOT NULL DEFAULT 'intermediate'
```

a été créé par la migration, alors que le mapping Doctrine déclarait le champ sans le `DEFAULT`.

Doctrine considérait donc le schéma SQLite différent du mapping et proposait une reconstruction de table uniquement pour supprimer ce défaut.

R32.2 aligne le mapping Doctrine sur la migration :

```php
#[ORM\Column(
    name: 'chord_analysis_level',
    length: 16,
    options: ['default' => 'intermediate'],
)]
```

Aucune donnée n'est modifiée.
Aucune migration supplémentaire n'est nécessaire.
Aucun `schema:update --force`.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R32_2_SCHEMA_DEFAULT_SYNC.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r32_2_schema_default_sync.py

php -l .\src\Domain\Song\Song.php
php .\tests\r32_2_schema_sync_contract.php

php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
php bin\console cache:clear
```

Attendu :

```text
5 R32.2 schema-sync checks passed.
```

Puis :

```text
Mapping
-------
[OK] The mapping files are correct.

Database
--------
[OK] The database schema is in sync with the mapping files.
```

et :

```powershell
php bin\console doctrine:schema:update --dump-sql
```

ne doit afficher aucune instruction SQL.
