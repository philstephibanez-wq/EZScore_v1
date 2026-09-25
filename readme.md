# EZScore_v1 — R18.1 Correctif mapping Doctrine GroupMember

Correctif ciblé de R18.

## Cause

R18 a ajouté sur `UserGroup` la relation inverse :

```php
#[ORM\OneToMany(mappedBy: 'group', targetEntity: GroupMember::class, orphanRemoval: true)]
private Collection $memberships;
```

mais l'association propriétaire `GroupMember::$group` n'indiquait pas le `inversedBy` correspondant.

Doctrine exige une déclaration bidirectionnelle cohérente.

## Correction

`GroupMember::$group` devient :

```php
#[ORM\ManyToOne(targetEntity: UserGroup::class, inversedBy: 'memberships')]
```

Aucune colonne ni table n'est modifiée.

## Migration

Aucune migration supplémentaire n'est nécessaire.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R18_1_GROUPMEMBER_MAPPING_FIX.zip" -C H:\EZScore_v1

php -l .\src\Domain\Group\GroupMember.php

php bin\console cache:clear
php bin\console doctrine:schema:validate
php bin\console doctrine:schema:update --dump-sql
```

Attendu :

```text
[OK] The mapping files are correct.
[OK] The database schema is in sync with the mapping files.
```

et `doctrine:schema:update --dump-sql` ne doit proposer aucun SQL.

Ne pas utiliser `doctrine:schema:update --force`.
