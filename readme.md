# EZScore_v1 R7.3 — Resend transactional mail

R7.3 connects the existing EZScore activation-mail workflow to the official Symfony Resend transport.

## Architecture

No business logic changes:

```text
RegistrationController
        |
        v
RegistrationMailer
        |
        v
Symfony Mailer
        |
        v
Resend API
```

`RegistrationMailer` remains provider-independent. Only the Symfony transport changes.

## 1. Install the Resend bridge

After extracting this ZIP:

```powershell
cd H:\EZScore_v1

composer update symfony/resend-mailer --with-all-dependencies
```

This updates `composer.lock` locally. Commit the resulting `composer.lock` with the other R7.3 files.

## 2. Create the Resend account

In Resend:

1. create the account;
2. add the domain that will send EZScore mail;
3. add the DNS records requested by Resend;
4. wait until the domain is shown as verified;
5. create an API key dedicated to EZScore.

Do not commit the API key.

## 3. Configure EZScore

Edit:

```text
H:\EZScore_v1\.env.local
```

Add:

```dotenv
MAILER_DSN="resend+api://re_YOUR_REAL_API_KEY@default"
MAILER_FROM="activation@your-verified-domain.tld"
```

`MAILER_FROM` must use a sender/domain accepted by the Resend account.

The existing EZScore code deliberately rejects a missing or `null://` mail transport. Delivery failures remain explicit.

## 4. Reload Symfony

```powershell
php bin\console cache:clear
php bin\console debug:config framework mailer
php bin\console debug:container --env-vars | Select-String "MAILER"
```

The API key may be masked in diagnostic output. Do not paste a real API key into GitHub, screenshots, issues, or chat logs.

## 5. Functional test

Use the public registration flow:

```text
http://127.0.0.1:8000/register
```

Expected sequence:

```text
create account
→ activation email sent by Resend
→ account blocked before verification
→ click activation link
→ email_verified_at populated
→ login allowed
```

Also test resend from:

```text
/registration/pending
```

## Files in R7.3

```text
composer.json
.env.example
readme.md
```

No migration.
No DATA modification.
No provider-specific code is introduced into the user/domain layer.
