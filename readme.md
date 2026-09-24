# EZScore_v1 — R9.2 correction wording inscription gratuite

Correction ciblée du texte public et du Profil.

## Règle fonctionnelle

L'inscription à EZScore est **toujours gratuite**.

Les éventuels abonnements futurs concernent des quotas et droits d'usage ; ils ne rendent pas l'inscription payante.

## Textes corrigés

FR :

```text
L’inscription à EZScore est gratuite.
```

Profil :

```text
L’inscription à EZScore est gratuite. Les offres d’abonnement et les quotas d’usage seront gérés séparément dans votre profil.
```

EN équivalent corrigé également.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_SIGNUP_FREE_COPY_R9_2.zip" -C H:\EZScore_v1

php bin\console cache:clear
```

Aucune migration.
Aucun CSS, Twig, JavaScript ou contrôleur modifié.
