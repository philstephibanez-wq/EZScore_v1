# EZScore_v1 R4.2 hotfix

Corrige la migration SQLite `Version20260924010000`.

## Cause

La migration R4 contenait des fragments `\n` dans des chaînes PHP entre apostrophes après des commentaires SQL `--(DC2Type:...)`.

En PHP, `\n` n'est pas converti en saut de ligne dans une chaîne entre apostrophes. SQLite considérait donc toute la fin de la requête comme un commentaire, ce qui provoquait :

`SQLSTATE[HY000]: General error: 1 incomplete input`

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R4_2_MIGRATION_HOTFIX.zip" -C H:\EZScore_v1
php bin\console cache:clear
php bin\console doctrine:migrations:status
php bin\console doctrine:migrations:migrate --no-interaction
```

Le ZIP est construit sans dossier racine : `migrations/Version20260924010000.php` écrase directement le fichier du projet.

Aucun changement de configuration PHP/SQLite n'est nécessaire.
