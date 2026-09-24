# EZScore_v1 R6.5.1 — Twig block hotfix

## Cause

`base.html.twig` déclarait `{% block body %}` deux fois :
- une fois dans la branche utilisateur authentifié ;
- une seconde fois dans la branche non authentifiée.

Twig interdit deux définitions du même bloc dans un même template, même si elles se trouvent dans des branches `{% if %}` distinctes.

Erreur :

```text
The block 'body' has already been defined
```

## Correction

Le template garde les deux wrappers conditionnels (`ez-shell` / `auth-shell`) mais ne déclare désormais qu'un seul :

```twig
{% block body %}{% endblock %}
```

Le header ChordU-like, le drawer, la recherche, FR/EN et le badge utilisateur restent inchangés.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R6_5_1_TWIG_BLOCK_HOTFIX.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console lint:twig templates
```

Puis relancer le serveur et faire `Ctrl+F5`.

Aucune migration. Aucune modification de DATA.
