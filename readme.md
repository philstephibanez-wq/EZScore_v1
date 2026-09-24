# EZScore_v1 — R8.1 correction accueil public

Cette livraison corrige le comportement constaté après R8 : un visiteur anonyme ne doit pas être envoyé vers la page de connexion lorsqu’il arrive sur EZScore ou change de langue.

## Comportement attendu

- `/` -> Répertoire public
- `/fr` -> Répertoire public FR
- `/en` -> Répertoire public EN
- `/fr/catalog` -> Répertoire public FR
- `/en/catalog` -> Répertoire public EN
- changement de langue sans référent exploitable -> Répertoire public
- utilisateur déjà connecté qui ouvre `/login` -> Répertoire
- déconnexion -> `/` puis Répertoire public

La page de connexion reste accessible explicitement depuis le bouton « Se connecter ».

Un lien « Retour au Répertoire » est également ajouté en bas de la page de connexion.

## Important

R8.1 ne modifie pas :
- le design du Répertoire public R8 ;
- la migration `published_at` ;
- les analyseurs ;
- les groupes/playlists ;
- le CSS global.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_PUBLIC_HOME_ROUTING_R8_1.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console debug:router
```

## Vérification

En navigation privée / déconnecté :

```text
https://ezscore.logandplay.com/
https://ezscore.logandplay.com/fr
https://ezscore.logandplay.com/fr/catalog
```

doivent toutes aboutir au Répertoire public, pas à `/fr/login`.
