# EZScore_v1 — R11.1a correction YAML traductions

Correction ciblée du YAML invalide introduit par R11.1.

## Cause

Ces traductions contiennent `: ` dans leur valeur :

```text
audio_help
audio_format
```

En YAML, une valeur scalaire contenant `: ` doit être quotée.

## Correction

Les valeurs FR/EN concernées sont maintenant entourées de quotes simples YAML.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_YAML_TRANSLATIONS_FIX_R11_1a.zip" -C H:\EZScore_v1

php bin\console lint:yaml translations
php bin\console cache:clear
php bin\console lint:twig templates
```

Aucune migration Doctrine.
Aucun changement fonctionnel hors correction syntaxique YAML.
