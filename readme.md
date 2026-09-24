# EZScore_v1 R7.5 — locale immédiate + dirty UI

- La locale persistée `users.locale` est réappliquée à chaque requête authentifiée.
- Si l'admin modifie sa propre langue, la session `_locale` est synchronisée avant redirection.
- `Save` est placé dans Actions à côté de Delete.
- Les formulaires `data-dirty-track` mettent leur bouton d'action en évidence dès qu'une valeur change.
- Le mécanisme est global et réutilisable sur tout le site.

Installation:

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R7_5_REALTIME_LOCALE_DIRTY_UI.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
php bin\console doctrine:schema:validate
```

Aucune migration.
