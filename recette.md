# EZScore_v1 — PLAN DE RECETTE FONCTIONNELLE

Version de référence : R19.

Convention :

- `[ ]` non testé
- `[x]` OK
- `[!]` KO
- `[~]` à revoir

Pour chaque KO noter : profil, URL, action, obtenu, attendu, capture et extrait de log.

## 1. Socle

- [ ] site démarre sans erreur
- [ ] `lint:yaml` OK
- [ ] `lint:twig` OK
- [ ] tous les PHP passent `php -l`
- [ ] `doctrine:migrations:status` cohérent
- [ ] migration R18 exécutée
- [ ] migration R19 exécutée
- [ ] `doctrine:schema:validate` OK
- [ ] `doctrine:schema:update --dump-sql` vide
- [ ] aucune utilisation de `schema:update --force`

## 2. Rôles globaux

### Lecteur

- [ ] peut créer une playlist personnelle
- [ ] peut inviter des utilisateurs inscrits à sa playlist
- [ ] ne peut pas créer de groupe
- [ ] ne peut pas importer ou éditer une chanson
- [ ] peut créer un événement personnel

### Éditeur

- [ ] hérite des droits Lecteur
- [ ] peut créer un groupe
- [ ] peut gérer ses groupes
- [ ] peut affecter ses playlists à ses groupes
- [ ] peut créer un événement pour un groupe qu'il gère

### Admin

- [ ] peut tout administrer
- [ ] voit groupes, propriétaires, playlists et attributs dans le back-office

## 3. Groupes

- [ ] création réservée Éditeur/Admin
- [ ] créateur devient `owner`
- [ ] ajout de plusieurs membres via popup
- [ ] retrait de plusieurs membres via popup
- [ ] filtres utilisateurs fonctionnent
- [ ] rôle contextuel `member`
- [ ] rôle contextuel `manager`
- [ ] rôle contextuel `owner`
- [ ] dernier owner impossible à retirer
- [ ] groupe conserve au moins un owner
- [ ] suppression du groupe ne supprime pas les playlists

## 4. Playlists et groupes

- [ ] une playlist garde toujours un propriétaire humain
- [ ] une playlist peut être affectée à un groupe
- [ ] une playlist peut être affectée à plusieurs groupes
- [ ] affectation de plusieurs playlists via popup
- [ ] retrait groupé des playlists
- [ ] retrait du groupe ne supprime pas la playlist
- [ ] membre du groupe voit automatiquement les playlists affectées
- [ ] aucune invitation playlist demandée pour cet accès
- [ ] retrait du membre retire immédiatement cet accès hérité

## 5. Playlists personnelles

- [ ] Lecteur crée sa playlist
- [ ] ajout de plusieurs chansons via popup
- [ ] retrait de plusieurs chansons via popup
- [ ] ordre des chansons conservé
- [ ] invitation personnelle possible
- [ ] invitation en attente ne donne pas accès
- [ ] invitation acceptée donne accès
- [ ] refus ne donne pas accès
- [ ] retrait du partage retire l'accès
- [ ] invité peut quitter le partage

## 6. Sélecteur modal générique

- [ ] recherche instantanée
- [ ] pagination serveur 25 résultats
- [ ] sélection multiple par checkbox
- [ ] sélectionner la page courante
- [ ] sélection conservée en changeant de page
- [ ] mode simple pour groupe/playlist d'un événement
- [ ] mode multiple pour participants
- [ ] aucune listbox volumineuse pour users/chansons/playlists

## 7. Événements — création

- [ ] entrée Événements visible dans le menu
- [ ] création avec titre
- [ ] date/heure de début obligatoire
- [ ] date/heure de fin facultative
- [ ] mode Présentiel
- [ ] mode À distance
- [ ] mode Hybride
- [ ] lieu persistant
- [ ] URL distante persistante
- [ ] description persistante
- [ ] créateur ajouté comme participant `accepted`

## 8. Événements — groupe

- [ ] association d'un groupe via popup en sélection simple
- [ ] seuls les groupes administrables sont proposés à un non-Admin
- [ ] association du groupe ajoute ses membres actifs comme participants
- [ ] créateur reste `accepted`
- [ ] autres membres commencent `invited`
- [ ] aucune `PlaylistInvitation` individuelle créée
- [ ] un membre du groupe peut voir l'événement
- [ ] un non-membre non invité ne peut pas voir l'événement

## 9. Événements — playlist

- [ ] association d'une playlist via popup
- [ ] un Éditeur ne voit que les playlists qu'il peut modifier
- [ ] si groupe + playlist et droits suffisants, `PlaylistGroup` est créé automatiquement
- [ ] les membres du groupe ont donc accès à la playlist sans invitation
- [ ] l'événement ne contourne jamais les droits chanson

## 10. Événements — session hors groupe

- [ ] événement sans groupe possible
- [ ] playlist facultative
- [ ] si playlist associée, seuls les users ayant déjà accès sont proposés
- [ ] propriétaire playlist éligible
- [ ] invité playlist accepté éligible
- [ ] membre d'un groupe ayant la playlist éligible
- [ ] user sans accès playlist non éligible

## 11. Participants et RSVP

- [ ] ajout multiple de participants
- [ ] retrait multiple de participants
- [ ] créateur impossible à retirer via bulk remove
- [ ] statut `invited`
- [ ] réponse `accepted`
- [ ] réponse `declined`
- [ ] réponse `maybe`
- [ ] réponse persistée après reconnexion

## 12. Notifications EZScore / email

- [ ] les invitations apparaissent dans `Mes invitations`
- [ ] ouverture de l'invitation affiche l'événement
- [ ] email envoyé quand SMTP est configuré
- [ ] lien email mène au bon événement
- [ ] locale du destinataire utilisée dans l'email
- [ ] échec SMTP n'annule pas l'invitation persistée
- [ ] `email_notified_at` renseigné uniquement après envoi réussi

## 13. Partage réseaux sociaux

- [ ] bouton WhatsApp présent
- [ ] WhatsApp ouvre un message prérempli avec titre/date/lien
- [ ] bouton Facebook présent
- [ ] Facebook partage l'URL de l'événement
- [ ] bouton X présent
- [ ] X prépare titre + URL
- [ ] aucun numéro de téléphone requis
- [ ] aucun SMS envoyé

## 14. Web Push / connecteurs futurs

Non implémentés en R19, à ne pas considérer comme disponibles.

- [ ] futur Web Push avec VAPID + Service Worker + consentement
- [ ] futur connecteur Telegram Bot optionnel
- [ ] futur connecteur Discord Webhook optionnel

## 15. Modification / cycle de vie événement

- [ ] modification titre
- [ ] modification horaires
- [ ] modification mode
- [ ] modification lieu
- [ ] modification lien distant
- [ ] statut Brouillon
- [ ] statut Planifié
- [ ] statut Annulé
- [ ] statut Terminé
- [ ] suppression événement cascade sur participants

## 16. I18N

- [ ] interface événement FR
- [ ] interface événement EN
- [ ] email événement FR
- [ ] email événement EN
- [ ] aucune clé brute `event.xxx`

## 17. Volume

- [ ] 100+ utilisateurs dans picker sans page HTML énorme
- [ ] 500+ chansons dans picker sans listbox
- [ ] 100+ playlists dans picker
- [ ] pagination et recherche restent réactives
- [ ] ajout/retrait groupé reste stable

## 18. Validation finale

- [ ] aucun bug bloquant
- [ ] ACL groupe/playlist cohérentes
- [ ] événements cohérents
- [ ] invitations personnelles cohérentes
- [ ] accès automatique groupe cohérent
- [ ] Doctrine synchronisé
- [ ] recette validée

Date :
Version / commit :
Testeur :
Commentaires :
