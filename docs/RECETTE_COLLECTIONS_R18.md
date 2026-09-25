# Recette R18 — Groupes / Playlists / sélecteurs de collections

## Migration

- [ ] migration R18 exécutée
- [ ] anciennes playlists personnelles conservent leur propriétaire
- [ ] anciennes playlists de groupe sont affectées au groupe correspondant
- [ ] anciennes playlists de groupe conservent un propriétaire humain via `created_by`
- [ ] `doctrine:schema:validate` OK
- [ ] `doctrine:schema:update --dump-sql` vide

## Lecteur

- [ ] crée une playlist personnelle
- [ ] ne peut pas créer de groupe
- [ ] ajoute plusieurs chansons via popup
- [ ] retire plusieurs chansons via popup
- [ ] recherche et filtres chanson fonctionnent
- [ ] invite plusieurs inscrits via popup
- [ ] invitation en attente ne donne pas accès
- [ ] acceptation donne accès en lecture
- [ ] retrait du partage retire l'accès

## Éditeur

- [ ] crée un groupe
- [ ] devient owner
- [ ] ajoute plusieurs membres via popup
- [ ] retire plusieurs membres via popup
- [ ] le dernier owner ne peut pas être retiré
- [ ] affecte plusieurs de ses playlists au groupe
- [ ] retire plusieurs playlists du groupe
- [ ] les playlists ne sont pas supprimées lorsqu'elles sont retirées du groupe
- [ ] la suppression du groupe ne supprime pas les playlists

## Membre du groupe

- [ ] voit automatiquement les playlists affectées
- [ ] aucune invitation playlist n'est demandée
- [ ] perd l'accès après retrait du groupe
- [ ] ne peut pas modifier une playlist dont il n'est pas propriétaire

## Ergonomie

- [ ] aucune grosse listbox chanson/user/playlist
- [ ] recherche temporisée
- [ ] filtres
- [ ] pagination serveur
- [ ] checkbox par ligne
- [ ] sélectionner la page
- [ ] sélection multi-pages conservée
- [ ] ajout groupé
- [ ] retrait groupé
