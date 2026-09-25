# Recette ACL R16.2

## Lecteur inscrit

- [ ] peut creer une playlist personnelle
- [ ] ne peut pas creer de groupe
- [ ] ne peut pas rattacher sa playlist a un groupe
- [ ] peut ajouter, retirer et reordonner les chansons auxquelles il a acces
- [ ] peut inviter un autre utilisateur deja inscrit
- [ ] l'invite est choisi par son nom affiche
- [ ] une invitation en attente ne donne aucun acces
- [ ] l'invite peut accepter ou refuser
- [ ] apres acceptation, la playlist est visible en lecture seule
- [ ] l'invite ne peut pas ajouter, retirer ou reordonner les chansons
- [ ] le proprietaire peut retirer le partage
- [ ] l'invite peut quitter la playlist
- [ ] une playlist partagee ne donne pas acces a une chanson normalement interdite

## Editeur

- [ ] herite des droits Lecteur via Symfony role_hierarchy
- [ ] peut creer un groupe
- [ ] le createur devient owner du groupe
- [ ] peut administrer les groupes selon GroupVoter

## Admin

- [ ] herite des droits Editeur puis Lecteur
- [ ] tous les privileges ACL testes sont accordes

## Doctrine

- [ ] migrations au dernier niveau
- [ ] doctrine:schema:validate = mapping OK + database schema in sync
- [ ] doctrine:schema:update --dump-sql = aucun SQL
