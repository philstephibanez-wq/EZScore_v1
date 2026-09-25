# Recette R19.1 — Type Session

- [ ] Le menu affiche `Sessions`, jamais `Événements`.
- [ ] La page affiche `Sessions`.
- [ ] Le formulaire affiche `Nouvelle session`.
- [ ] Les messages de succès parlent de session.
- [ ] Les emails parlent de session.
- [ ] Une nouvelle ligne `events` reçoit `type = session`.
- [ ] Les événements R19 déjà existants sont migrés avec `type = session`.
- [ ] `Event::getType()` retourne `EventType::Session`.
- [ ] Aucun autre type d'événement n'est proposé dans l'UI.
- [ ] `doctrine:schema:validate` est OK.
- [ ] `doctrine:schema:update --dump-sql` est vide.
