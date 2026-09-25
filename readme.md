# EZScore_v1

Symfony application for managing songs, editors, ratings, groups and playlists, with a separate Python musical-analysis service connected through HTTP/JSON.

## R14 — Repository Hardening & Contracts

This delivery implements audit items 1–5:

1. Git/security hardening and APP_SECRET rotation procedure.
2. Removal of obsolete source/runtime files.
3. Group creation by every connected user, automatic owner membership, and last-owner protection.
4. Real ordered `Playlist -> Song` relation through `PlaylistItem`, with add/remove/up/down actions and access checks.
5. Executable contract/domain checks plus GitHub Actions CI.

Python analysis is deliberately **not** connected by this delivery. The functional recipe remains the next validation gate.

## Install on the current repository

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_HARDENING_GROUPS_PLAYLISTS_CI_R14.zip" -C H:\EZScore_v1

powershell -ExecutionPolicy Bypass -File .\scripts\apply_r14_hardening.ps1

php bin\console lint:yaml config translations
php bin\console lint:twig templates
Get-ChildItem src,migrations,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

php bin\console doctrine:migrations:migrate --no-interaction
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\run.php
```

Expected Doctrine result after migration:

```text
Mapping OK
Database schema in sync
```

and `doctrine:schema:update --dump-sql` must not propose additional CREATE/ALTER/DROP SQL.

## Security note

The previously committed `.env.dev` contained a real-looking `APP_SECRET` and the repository is public. R14 sanitizes `.env.dev`; `apply_r14_hardening.ps1` generates a new random local secret in `.env.local`.

The old value remains visible in Git history, but after rotation it is no longer a valid application secret. Do not reuse it in any environment.

## Git cleanup

The hardening script removes obsolete working-tree files and untracks files under `public/uploads/` while keeping their local bytes. `.gitignore` now prevents future runtime covers from being committed.

After all validation is green, inspect before committing:

```powershell
git status --short
git diff -- . ':!composer.lock'
git diff --cached --name-status
```

The script does **not** commit and does **not** push.

## Group contract

- Reader, Editor and Admin users may create a group.
- The creator is immediately persisted as `owner`.
- `owner` and `manager` may administer the group.
- Only owner/Admin may promote/demote/remove an owner.
- A group cannot lose its last owner.
- Admin retains global administration rights.

## Playlist contract

`PlaylistItem` stores:

- playlist;
- song;
- added-by user;
- ordered position;
- creation timestamp.

Database guarantees one occurrence of a given song per playlist.

A user may only add a song they can access through the catalog rules:

- Admin: all songs;
- Editor: own songs + published songs;
- Reader: published songs.

Only a user allowed to manage the playlist may add, remove or reorder its songs.

## CI

`.github/workflows/ci.yml` runs on `master` pushes and pull requests with PHP 8.4:

- `composer validate --strict`;
- `composer audit --locked`;
- PHP syntax validation;
- YAML lint;
- Twig lint;
- contract/domain tests;
- migrations on a clean SQLite database;
- Doctrine schema validation;
- verification that no pending schema SQL remains.

## Architecture constraints preserved

- no `shell_exec` / direct PHP -> Python execution;
- no audio base64 in JSON;
- Symfony owns security/business/persistence/orchestration;
- Python remains calculation-only;
- analysis jobs remain separate from song workflow states;
- no fabricated musical-analysis data.
