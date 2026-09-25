# EZScore_v1 — R9.4 affichage « Se souvenir de moi »

Correction ciblée : le mécanisme `remember_me` de R9.3 était configuré, mais la case n'était pas visible dans l'UI constatée.

## Correction

- présence forcée du champ `_remember_me` dans `templates/auth/login.html.twig`;
- style explicite de checkbox dans `authentication.css`;
- cache-busting CSS `?v=20260925r94`.

La case est affichée entre le mot de passe et le bouton « Se connecter ».

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_REMEMBER_ME_VISIBLE_R9_4.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
```

Puis faire un rechargement forcé du navigateur :

```text
Ctrl+F5
```

## Vérification locale du template

```powershell
Select-String -Path .\templates\auth\login.html.twig -Pattern "_remember_me"
```

Résultat attendu : une ligne contenant :

```text
name="_remember_me"
```

Aucune migration.
Aucun changement Doctrine.
Aucun changement fonctionnel hors affichage de la case.
