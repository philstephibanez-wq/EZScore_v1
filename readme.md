# EZScore_v1 — renommage CSS sémantique + hover/dirty global

Les noms de versions `r6.css`, `r7.css`, `r7-admin.css`, `r7-interactions.css` sont supprimés de l'architecture active.

Nouveaux noms :

```text
base.css
layout.css
authentication.css
admin-users.css
interactions.css
```

Le JS du dirty state devient :

```text
dirty-tracker.js
```

Le hover est défini globalement dans `interactions.css` et chargé en dernier.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_UI_CSS_SEMANTIC_HOVER.zip" -C H:\EZScore_v1

Remove-Item .\public\assets\css\r6.css -ErrorAction SilentlyContinue
Remove-Item .\public\assets\css\r7.css -ErrorAction SilentlyContinue
Remove-Item .\public\assets\css\r7-admin.css -ErrorAction SilentlyContinue
Remove-Item .\public\assets\css\r7-interactions.css -ErrorAction SilentlyContinue
Remove-Item .\public\assets\css\app.css -ErrorAction SilentlyContinue

php bin\console cache:clear
php bin\console lint:twig templates
```

## Vérification attendue

- Survol de n'importe quel bouton : halo bleu + légère élévation.
- Survol des liens interactifs principaux : même feedback.
- Modification d'un formulaire `data-dirty-track` : bouton Save orange.
- Retour à la valeur initiale : bouton Save neutre.
