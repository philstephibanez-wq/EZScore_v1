# EZScore_v1 R7.5.1 — hotfix cache assets dirty UI

Base vérifiée sur le HEAD GitHub :

```text
d0b9045eb2c2b00a59ba76f1a3dc413ae612f9ec
```

Le code dirty est déjà présent dans `public/assets/js/app.js` et `public/assets/css/r7-interactions.css`.

Le problème restant est cohérent avec une ancienne version de ces assets encore servie/cachée.

Ce hotfix force une nouvelle URL :

```text
/assets/js/app.js?v=R7.5.1
/assets/css/r7-interactions.css?v=R7.5.1
```

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R7_5_1_DIRTY_ASSET_CACHEFIX.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console lint:twig templates
```

Ensuite recharger `Administration > Utilisateurs`.

Test attendu :

```text
Save neutre
changer FR -> EN
Save devient orange/highlight
remettre EN -> FR avant de sauver
Save redevient neutre
```

Aucune migration.
