# EZScore_v1 R7.1 — schema locale hotfix

Le delta Doctrine venait uniquement de `users.locale`.

La base réelle contient :

```sql
locale VARCHAR(2) NOT NULL
```

alors que le mapping R7 demandait :

```sql
locale VARCHAR(2) DEFAULT 'fr' NOT NULL
```

R7 avait ajouté par erreur `options: ['default' => 'fr']` au mapping Doctrine.

La valeur par défaut métier reste bien `fr` dans l'entité :

```php
private string $locale = 'fr';
```

Il n'est pas nécessaire d'imposer un DEFAULT SQL supplémentaire.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R7_1_SCHEMA_LOCALE_HOTFIX.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

Résultat attendu :

```text
Mapping  [OK]
Database [OK]
```

et `doctrine:schema:update --dump-sql` ne doit plus proposer de reconstruction de `users`.

Aucune migration et aucune modification des données.
