# EZScore_v1 — R19 Événements / RSVP / notifications

Prérequis : R18 + correctif R18.1 installés.

## Fonction livrée

R19 ajoute la notion d'événement :

```text
Event
├─ créateur
├─ date / heure
├─ présentiel / distance / hybride
├─ groupe optionnel
├─ playlist optionnelle
└─ participants + RSVP
```

Cas principal :

```text
Répétition du 30/10/2026
Formation A
Playlist Répétition 30/10/2026
Participants = membres de Formation A
```

## Règles métier

- Un événement de groupe ajoute les membres actifs comme participants.
- L'accès playlist du groupe reste automatique : aucune `PlaylistInvitation` n'est créée.
- Si groupe + playlist sont associés et que l'organisateur possède les ACL nécessaires, la relation `PlaylistGroup` est créée automatiquement.
- Un événement sans groupe peut inviter des utilisateurs.
- Si une playlist est associée à un événement hors groupe, seuls les utilisateurs ayant déjà accès à cette playlist sont éligibles.
- RSVP : `invited`, `accepted`, `declined`, `maybe`.
- Le créateur de l'événement est automatiquement participant `accepted`.

## Notifications R19

Implémenté :

- notification interne via la liste `Mes invitations` ;
- email automatique lors de l'ajout d'un participant ;
- échec email non bloquant : l'invitation reste persistée ;
- partage manuel WhatsApp ;
- partage manuel Facebook ;
- partage manuel X.

Non implémenté dans R19, volontairement :

- SMS ;
- Web Push/PWA réel ;
- Telegram Bot ;
- Discord Webhook.

Le Web Push et les connecteurs automatiques sont inscrits au cahier des charges comme étapes suivantes. Aucun faux push local n'est présenté comme un Web Push.

## Ergonomie

Le sélecteur modal R18 est étendu :

- `single` pour choisir un groupe ou une playlist ;
- `multiple` pour les participants ;
- recherche ;
- pagination serveur ;
- checkbox ;
- ajout/retrait groupé.

## Cahier des charges

Mis à jour :

`docs/CAHIER_DES_CHARGES.md`

## Recette

La recette racine est mise à jour :

`recette.md`

Une recette ciblée existe aussi :

`docs/RECETTE_EVENTS_R19.md`

## Migration

R19 ajoute :

```text
events
event_participants
```

Migration :

`migrations/Version20260925213000.php`

Aucune modification destructive des chansons, groupes, playlists ou utilisateurs.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_EVENTS_NOTIFICATIONS_R19.zip" -C H:\EZScore_v1

php bin\console lint:yaml config translations
php bin\console lint:twig templates

Get-ChildItem src,migrations,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php bin\console doctrine:migrations:status
php bin\console doctrine:migrations:migrate --no-interaction

php bin\console cache:clear
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\events_contract.php
```

Attendu :

- migration `Version20260925213000` exécutée ;
- mapping Doctrine valide ;
- schéma synchronisé ;
- `doctrine:schema:update --dump-sql` vide.

Ne jamais utiliser :

```text
php bin\console doctrine:schema:update --force
```

## Recette courte

1. Éditeur crée un événement.
2. Il associe un groupe avec la popup.
3. Les membres du groupe deviennent participants.
4. Il associe une playlist.
5. Vérifier que la playlist est visible par les membres sans invitation playlist.
6. Un membre répond Présent.
7. Un autre répond Absent.
8. Vérifier l'email si SMTP actif.
9. Vérifier WhatsApp / Facebook / X.
10. Créer un événement sans groupe avec une playlist partagée.
11. Vérifier que le picker participants ne propose que les utilisateurs ayant accès à cette playlist.
