# EZScore_v1 — R22.1 Mailing nouvelle chanson ASYNCHRONE

Ce livrable **remplace R22**. Il est cumulatif : il contient le mailing, les préférences utilisateur, le journal anti-doublon et la file asynchrone.

Si R22 a déjà été installé, seule la migration R22.1 supplémentaire sera exécutée.

## Fonctionnement

Lorsqu'une chanson passe réellement de non-publiée à `Published` :

```text
Publication HTTP
    ↓
SongPublicationQueue
    ↓
song_publication_mail_jobs
    ↓
réponse HTTP immédiate
```

Aucun email n'est envoyé dans la requête web.

Le worker séparé fait ensuite :

```text
song_publication_mail_jobs
    ↓
sélection destinataires actifs + vérifiés + opt-in
    ↓
Symfony Mailer
    ↓
song_publication_notifications
```

## Anti-doublon et reprise

Le journal destinataire conserve :

```text
song_id
user_id
sent_at
failed_at
last_error
```

avec unicité `(song_id, user_id)`.

Le worker :

- ne renvoie jamais un destinataire déjà `sent_at` ;
- reprend un job abandonné après 15 minutes ;
- libère un job en erreur ;
- planifie un retry avec backoff progressif ;
- reprend uniquement les destinataires non encore envoyés.

## Worker

Lancer en continu :

```powershell
cd H:\EZScore_v1
php bin\console app:mailing:worker
```

ou :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start_mailing_worker.ps1
```

Pour un test unique :

```powershell
php bin\console app:mailing:worker --once
```

Options :

```text
--once
--sleep=2
--limit=10
```

## Pourquoi pas Messenger dans ce lot ?

L'asynchronisme est réel, mais utilise la BDD Doctrine déjà présente. Cela évite d'ajouter maintenant `symfony/messenger` + un transport supplémentaire juste pour ce mailing.

Le contrat métier reste indépendant : cette file pourra être remplacée plus tard par Messenger sans toucher au workflow de publication.

## Migrations

Cumulatif :

```text
Version20260925230000
  users.notify_new_songs
  song_publication_notifications

Version20260925231000
  song_publication_mail_jobs
```

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_NEW_SONG_MAILING_ASYNC_R22_1.zip" -C H:\EZScore_v1

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

php tests\publish_mailing_async_contract.php
```

Attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et aucun SQL dans :

```powershell
php bin\console doctrine:schema:update --dump-sql
```

Ne pas utiliser `doctrine:schema:update --force`.

## Premier test

Avant de tester, laissez le worker arrêté.

1. Publier une chanson.
2. La page doit répondre immédiatement.
3. Aucun mail ne doit encore partir.
4. Lancer :

```powershell
php bin\console app:mailing:worker --once
```

5. Le mail doit partir.
6. Vérifier le journal et `completed_at`.

Ensuite lancer le worker continu pour le fonctionnement normal.
