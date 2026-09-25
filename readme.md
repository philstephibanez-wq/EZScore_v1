# EZScore_v1 — R20.1 Correctif Doctrine `events.type`

## Diagnostic

Les migrations sont bien à jour :

```text
Current = Version20260925220000
Latest  = Version20260925220000
New     = 0
```

Le SQL proposé par Doctrine reconstruit uniquement la table `events`.

La cause est précise :

R19.1 a créé la colonne avec :

```sql
type VARCHAR(32) NOT NULL DEFAULT 'session'
```

alors que le mapping Doctrine de `Event::$type` déclare une colonne `NOT NULL` sans option `DEFAULT`.

Doctrine voit donc un écart permanent entre le schéma SQLite et le mapping, même si les valeurs sont correctes.

## Correction

R20.1 reconstruit uniquement la table `events` pour obtenir :

```sql
type VARCHAR(32) NOT NULL
```

Les valeurs `session` existantes sont intégralement conservées.

Les clés étrangères et les quatre index `events` sont recréés à l'identique.

Aucun changement PHP métier ou UI.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R20_1_EVENT_TYPE_SCHEMA_FIX.zip" -C H:\EZScore_v1

php -l .\migrations\Version20260925223000.php
php tests\event_type_schema_contract.php

php bin\console doctrine:migrations:status
php bin\console doctrine:migrations:migrate --no-interaction

php bin\console cache:clear

php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

Attendu après migration :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et :

```powershell
php bin\console doctrine:schema:update --dump-sql
```

ne doit plus produire de SQL.

## Important

Ne pas utiliser :

```text
php bin\console doctrine:schema:update --force
```
