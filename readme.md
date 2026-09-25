# EZScore_v1 — R10.2 bouton Import propre

Correction ciblée de l'affichage du bouton Import dans le Répertoire.

## Cause

Le template R10 chargeait encore :

```text
/assets/css/catalog.css?v=20260925r8
```

alors que les styles du bouton avaient été ajoutés ensuite.

Selon le cache navigateur/proxy, le bouton pouvait donc apparaître comme :

```text
ImporterMP3 + fiche chanson
```

sans espacement ni mise en forme.

## Correction

- version CSS forcée à `20260925r102`;
- structure du bouton rendue explicite;
- icône `+`;
- titre et sous-texte sur deux lignes;
- responsive mobile.

Affichage attendu :

```text
[ + ]  Importer
       MP3 + fiche chanson
```

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_IMPORT_BUTTON_UI_R10_2.zip" -C H:\EZScore_v1

php bin\console cache:clear
```

Puis rechargement forcé navigateur :

```text
Ctrl+F5
```

Aucune migration.
Aucun changement Doctrine.
Aucune donnée modifiée.
