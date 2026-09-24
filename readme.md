# EZScore_v1 — contrat global de hover

Base vérifiée sur le HEAD GitHub :

```text
c7ffe263a59d801eb9302dd49d8a2afe06588f4b
```

Correction structurelle : `interactions.css` est maintenant chargé **après tous les CSS spécifiques aux pages**.

Le hover s'applique à tous les éléments interactifs principaux :

```text
a[href]
button
input submit/button
input éditables
checkbox/radio
select
summary
label[for]
[role="button"]
```

Les cartes du dashboard ont un feedback volontairement plus visible.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_GLOBAL_INTERACTIVE_HOVER.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
```

Test attendu :
- Dashboard : Répertoire / Playlists / Groupes / Utilisateurs réagissent au survol.
- Header : logo, langues, avatar, recherche réagissent.
- Formulaires : champs, selects, checkbox et boutons réagissent.
- Menu latéral : tous les liens réagissent.
- Dirty : Save reste orange tant qu'une modification n'est pas sauvée.

Aucune migration.
