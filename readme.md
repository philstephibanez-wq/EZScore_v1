# EZScore_v1 R5.3 — alignement schéma Doctrine

La sortie de `doctrine:schema:update --dump-sql` montre que le mapping ORM est désormais chargé correctement, mais que la migration initiale ne correspond pas exactement au schéma attendu par Doctrine.

Différences corrigées :

- `users.password` : `CLOB` -> `VARCHAR(255)` ;
- index FK de `group_members` : noms attendus par Doctrine ;
- index FK de `playlists` : nom attendu par Doctrine ;
- `ON UPDATE NO ACTION` explicitement présent sur les clés étrangères SQLite.

Comme EZScore_v1 est encore au stade de fondation et qu'aucune donnée applicative n'a encore besoin d'être conservée, la correction propre consiste à corriger la migration initiale puis recréer la base SQLite.

## Installation

```powershell
cd H:\EZScore_v1
tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R5_3_SCHEMA_ALIGNMENT.zip" -C H:\EZScore_v1
```

## Recréation de la base de développement

Ne faire ceci que tant qu'aucun compte ou donnée EZScore à conserver n'a été créé :

```powershell
Remove-Item .\data\ezscore_v1.sqlite -Force -ErrorAction SilentlyContinue

php bin\console cache:clear
php bin\console doctrine:migrations:migrate --no-interaction
php bin\console doctrine:schema:validate
```

Résultat attendu :

```text
Mapping
-------
[OK] The mapping files are correct.

Database
--------
[OK] The database schema is in sync with the mapping files.
```

Ne pas utiliser `doctrine:schema:update --force`.
