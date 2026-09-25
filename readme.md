# EZScore_v1 - R16.2 Native Symfony ACL + current database compatibility

This is the cumulative R16 delivery. It supersedes R15, R16 and R16.1.

## Authorization architecture

The ACL model is inspired by OPUS, but implemented entirely with native Symfony Security:

- role definitions / hierarchy: `config/packages/security.yaml`
- user role assignment: persisted in `users.roles`
- resources: Doctrine entities
- privileges: `src/Security/Acl/AclPrivilege.php`
- contextual decisions: Symfony Voters
- controllers: `denyAccessUnlessGranted(...)`
- Twig: `is_granted(...)`

No generic ACL rules table is introduced.

## Existing database

This delivery is designed to keep the existing EZScore SQLite database.

It does NOT replace, recreate or reset the database.

The migration `Version20260925150000` creates `playlist_invitations` only when it has not already been executed.

On a database where R16 was already migrated, Doctrine reports no new migration.

R16.2 fixes the mapping/index mismatch found after R16:

- migration index: `IDX_PLAYLIST_INVITATION_BY`
- Doctrine mapping: now declares the exact same index name

Therefore the existing table is retained and Doctrine should no longer propose a SQLite table rebuild.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_NATIVE_SYMFONY_ACL_R16_2.zip" -C H:\EZScore_v1

powershell -ExecutionPolicy Bypass -File .\scripts\apply_r16_2_acl.ps1

php bin\console lint:yaml config translations
php bin\console lint:twig templates

Get-ChildItem src,migrations,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php bin\console doctrine:migrations:status
php bin\console doctrine:migrations:migrate --no-interaction

php bin\console cache:clear
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\run.php
```

Expected on the current migrated database:

```text
doctrine:migrations:status
Current = Version20260925150000
New = 0
```

Expected after cache clear:

```text
doctrine:schema:validate
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

And:

```text
php bin\console doctrine:schema:update --dump-sql
```

must produce no schema SQL.

Do NOT run `doctrine:schema:update --force`.

## Functional scope

### Reader

- personal playlists only
- no group creation
- invite an already registered user by display name
- pending / accepted / declined / cancelled invitation lifecycle
- no playlist access before acceptance
- accepted share is read-only
- owner can revoke sharing
- invited user can leave sharing
- shared playlists never bypass song access rules

### Editor

- inherits Reader rights through Symfony `role_hierarchy`
- can create groups
- group rights are resolved by `GroupVoter`

### Admin

- inherits Editor then Reader
- global administrative override through native Symfony Security

## ACL documentation

See:

`docs/ACL.md`

and the focused acceptance checklist:

`docs/RECETTE_ACL_R16_2.md`
