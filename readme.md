# EZScore_v1 — R12.2 correction YAML validée

Cette livraison corrige uniquement les deux traductions YAML invalides réintroduites dans R12.

## Cause

Les valeurs suivantes contiennent `: ` :

```text
catalog.import.audio_help
catalog.import.validation.audio_format
```

Elles doivent être entourées de quotes YAML.

## Validation

Les deux fichiers complets FR/EN ont été parsés avant création du ZIP.

## Installation

Télécharger d'abord le ZIP, puis :

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R12_2_YAML_FIX.zip" -C H:\EZScore_v1

php bin\console lint:yaml translations
php bin\console cache:clear
php bin\console lint:twig templates
php bin\console debug:router
```

Résultat attendu :

```text
[OK] All YAML files contain valid syntax.
```

Aucune migration Doctrine.
