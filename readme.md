# EZScore_v1 — R16 Native Symfony ACL

Base : `master` après R14.

Cette livraison remplace les règles d'autorisation dispersées par une couche native Symfony Security, inspirée du modèle OPUS ACL sans réutiliser son moteur historique.

## Principes

- Symfony Security reste l'unique moteur.
- `ROLE_*` = rôles globaux.
- Doctrine = relations métier.
- Voters Symfony = conditions contextuelles.
- Contrôleurs = `denyAccessUnlessGranted`.
- Twig = `is_granted`.
- refus par défaut.

## Ajouts

- `AclPrivilege`
- `PlaylistVoter`
- `GroupVoter`
- `SongVoter`
- `PlaylistInvitation`
- `PlaylistInvitationStatus`
- rôle Symfony hiérarchique :
  - Editor hérite Reader
  - Admin hérite Editor
- partage de playlist avec un inscrit par nom affiché
- invitation acceptée/refusée/annulée
- partage accepté en lecture seule
- Lecteur interdit de création de groupe
- documentation `docs/ACL.md`

## Important

Cette version SUPPLANTE la tentative R15.

Ne relancez pas `scripts/update_recette_r15.ps1`.
Le script R16 supprime ce fichier s'il est encore présent.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_NATIVE_SYMFONY_ACL_R16.zip" -C H:\EZScore_v1

powershell -ExecutionPolicy Bypass -File .\scripts\apply_r16_acl.ps1

php bin\console lint:yaml config translations
php bin\console lint:twig templates

Get-ChildItem src,migrations,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php bin\console doctrine:migrations:migrate --no-interaction
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql

php tests\run.php
php bin\console cache:clear
```

Attendu :

- `doctrine:schema:validate` : mapping + base OK ;
- `doctrine:schema:update --dump-sql` : aucun SQL inattendu ;
- `tests/run.php` : tous les contrôles OK.

## Test fonctionnel ciblé

### Lecteur A

- crée une playlist personnelle ;
- ajoute une chanson publiée ;
- ne voit aucun formulaire de création de groupe ;
- invite Lecteur B par nom affiché.

### Lecteur B

- voit l'invitation mais pas la playlist avant acceptation ;
- peut refuser ;
- après nouvelle invitation, peut accepter ;
- après acceptation voit la playlist en lecture seule ;
- ne peut ni ajouter, ni retirer, ni réordonner les chansons ;
- peut quitter le partage.

### Lecteur A

- peut retirer le partage ;
- peut supprimer sa playlist.

## Recette

La recette globale doit être révisée après validation de ce noyau ACL. Cette livraison n'écrase pas `recette.md` pour ne pas perdre les annotations déjà saisies.
