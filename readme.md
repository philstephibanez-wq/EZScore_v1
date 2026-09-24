# EZScore_v1 — URLs localisées + retour applicatif + hover global

Base GitHub vérifiée avant modification :

```text
6c0ca23b28cf7fc8f007459732e3ad7b9958d744
```

## 1. Langue dans l'URL

Les pages métier deviennent :

```text
/fr/dashboard
/fr/catalog
/fr/playlists
/fr/groups
/fr/admin/users
/fr/profile

/en/dashboard
/en/catalog
/en/playlists
/en/groups
/en/admin/users
/en/profile
```

La locale portée par l'URL est prioritaire pour le rendu.

Le sélecteur FR/EN :
- persiste `users.locale`;
- met à jour la session;
- remplace le préfixe `/fr` ou `/en` dans l'URL courante.

## 2. Google OAuth

Le callback reste strictement :

```text
/connect/google/check
```

Aucun changement n'est requis dans Google Console.

## 3. E-mail d'activation

Le lien envoyé contient maintenant la langue persistée :

```text
/fr/activate/<token>
```

ou :

```text
/en/activate/<token>
```

## 4. Bouton Retour

Toutes les pages authentifiées sauf le dashboard affichent un bouton Retour.

Le workspace morceau revient au Répertoire.
Les autres pages reviennent au Tableau de bord.

## 5. Hover

Le feedback pointeur est intégré directement dans `public/assets/js/app.js`, déjà utilisé par l'application.

Le style est appliqué inline au survol afin qu'aucun CSS spécifique de page ne puisse l'annuler.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_LOCALIZED_URLS_NAV_HOVER.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
php bin\console debug:router
```

Aucune migration.

Vérifier ensuite :

```text
https://ezscore.logandplay.com/fr/dashboard
https://ezscore.logandplay.com/en/dashboard
```
