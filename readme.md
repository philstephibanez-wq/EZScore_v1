# EZScore_v1 — R10.1 correction boucle Doctrine

## Cause exacte

La sortie :

```text
php bin\console doctrine:schema:update --dump-sql
```

montre que Doctrine veut reconstruire uniquement la table `songs`.

La différence vient de `status`.

La migration R10 avait créé :

```sql
status VARCHAR(16) NOT NULL DEFAULT 'editing'
```

alors que le mapping Doctrine de `Song::$status` décrit :

```text
VARCHAR(16) NOT NULL
```

sans valeur par défaut SQL.

Doctrine considère donc le schéma différent à chaque validation.

## Correction

R10.1 reconstruit `songs` exactement selon le mapping Doctrine et retire uniquement le `DEFAULT 'editing'`.

Les données sont conservées.

Les relations existantes vers `songs`, notamment `analysis_jobs` et `song_ratings`, sont protégées pendant la reconstruction par la désactivation temporaire des foreign keys SQLite.

La migration est volontairement non transactionnelle afin que :

```sql
PRAGMA foreign_keys = OFF
```

soit réellement appliqué par SQLite.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_DOCTRINE_STATUS_DEFAULT_FIX_R10_1.zip" -C H:\EZScore_v1

php bin\console cache:clear
php bin\console doctrine:migrations:migrate
```

Puis contrôle obligatoire :

```powershell
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

Résultat attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et `--dump-sql` ne doit afficher aucune requête.

## Important

Ne pas exécuter :

```text
doctrine:schema:update --force
```

Cette livraison ne modifie aucun contrôleur, template, CSS, traduction ou donnée métier.
