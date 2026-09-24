# EZScore_v1 — correction i18n profil R5

Livrable ciblé : **aucun CSS, aucun Twig, aucun JavaScript, aucun contrôleur modifié**.

## Correction

Ajout de la dernière clé manquante dans `translations/messages.fr.yaml` et `translations/messages.en.yaml` :

- `profile.title` → `Profil` (FR)
- `profile.title` → `Profile` (EN)

Le livrable inclut également les 5 clés `layout.*` de la R4 afin que les fichiers de traduction restent complets et cohérents.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_I18N_PROFILE_R5.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console debug:translation fr --domain=messages --only-missing
php bin\console debug:translation en --domain=messages --only-missing
```

Résultat attendu : aucune traduction `missing` dans le domaine `messages`.

Aucune migration.
