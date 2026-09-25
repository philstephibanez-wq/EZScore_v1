# EZScore_v1 — R19.1 Session comme type d'Event

Correctif / évolution ciblée au-dessus de R19.

## Principe

Le code reste générique :

```text
Event
EventParticipant
```

L'UI expose pour l'instant uniquement :

```text
Session
```

R19.1 ajoute donc un discriminateur :

```text
events.type = session
```

et l'enum :

```php
EventType::Session
```

Cela évite de renommer les entités maintenant puis de devoir les re-généraliser quand d'autres types d'événements apparaîtront.

## Migration

La migration `Version20260925220000.php` ajoute `events.type`.

Tous les événements R19 existants sont automatiquement considérés comme des sessions grâce à la valeur par défaut `session`.

## UI

Tous les libellés utilisateur deviennent :

- Sessions
- Nouvelle session
- Modifier la session
- Supprimer la session

Le terme `Event` reste interne au code et à la base.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_SESSION_EVENT_TYPE_R19_1.zip" -C H:\EZScore_v1

php bin\console lint:yaml translations
Get-ChildItem src,migrations,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

php bin\console doctrine:migrations:migrate --no-interaction
php bin\console cache:clear

php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\session_type_contract.php
```

Attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et aucun SQL proposé par `doctrine:schema:update --dump-sql`.

Ne pas utiliser `doctrine:schema:update --force`.
