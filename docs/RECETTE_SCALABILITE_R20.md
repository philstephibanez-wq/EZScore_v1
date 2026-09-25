# Recette ciblée R20 — Scalabilité des listes

Objectif : valider EZScore avec plusieurs centaines d'utilisateurs, groupes, playlists et sessions.

## Jeu de test recommandé

- 500 utilisateurs
- 100 groupes
- 300 playlists
- 1 000 chansons
- 200 sessions
- au moins un groupe avec 200 membres
- au moins une playlist avec 300 chansons
- au moins une session avec 200 participants

## Mesures fonctionnelles

- aucune page ne doit afficher l'intégralité du corpus par défaut ;
- l'index A-Z doit réduire immédiatement le corpus ;
- chaque recherche doit être appliquée côté serveur ;
- la pagination doit conserver tous les filtres ;
- les cartes Groupe et Playlist doivent afficher un aperçu borné ;
- les pickers restent paginés ;
- l'Admin doit retrouver un utilisateur, groupe ou playlist sans scroll massif ;
- la page Session doit retrouver un participant par nom ou RSVP.

## Contrôle Doctrine

```powershell
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

R20 ne modifie pas le schéma : aucun SQL supplémentaire n'est attendu.
