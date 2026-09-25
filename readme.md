# EZScore_v1 — R10.5 correctif consolidé Import

Corrige réellement les deux défauts visibles :
- Signature `Auto` absente ;
- bouton Import rendu comme du texte collé.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_IMPORT_UI_AUTO_R10_5.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
```

Puis `Ctrl+F5`.

## Contrôles

```powershell
Select-String -Path .\templates\layout\song\_metadata.html.twig -Pattern 'value="auto"'
Select-String -Path .\templates\catalog\index.html.twig -Pattern 'ez-import-cta'
```

Les deux doivent retourner une ligne.

Aucune migration Doctrine.
