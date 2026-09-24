# EZScore_v1 R7.2 — suppression administrateur d'un utilisateur

## Fonction

L'administrateur dispose maintenant d'un bouton **Supprimer** pour chaque utilisateur.

La suppression est définitive, avec confirmation navigateur et contrôle CSRF côté serveur.

## Règles d'intégrité

La suppression est refusée si :

- l'administrateur tente de supprimer son propre compte ;
- la cible est le dernier administrateur actif ;
- la cible possède des `analysis_jobs`, car `created_by` constitue une donnée d'audit et la base impose `ON DELETE RESTRICT`.

Lorsqu'une suppression est autorisée :

- les appartenances aux groupes disparaissent via `ON DELETE CASCADE` ;
- les morceaux édités par cet utilisateur conservent leurs données et `editor_id` devient `NULL` via `ON DELETE SET NULL` ;
- ses playlists personnelles sont supprimées ;
- les playlists de groupe qu'il a créées sont conservées et leur champ technique `created_by` est transféré à l'administrateur qui effectue la suppression.

Aucune donnée d'analyse n'est supprimée ou réattribuée silencieusement.

## MAILER_FROM

L'exception :

```text
Environment variable not found: "MAILER_FROM"
```

signifie que R7 est bien chargé mais que le transport mail n'est pas encore complètement configuré.

Dans `H:\EZScore_v1\.env.local`, fournir des valeurs réelles :

```dotenv
MAILER_DSN="smtp://USER:PASSWORD@smtp.example.com:587"
MAILER_FROM="no-reply@votre-domaine.tld"
```

Ne pas utiliser de valeur factice en production. `MAILER_FROM` doit être une adresse autorisée par le fournisseur SMTP.

Après modification :

```powershell
php bin\console cache:clear
```

## Installation R7.2

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R7_2_ADMIN_DELETE_USER.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
php bin\console doctrine:schema:validate
```

Aucune migration dans ce lot.
