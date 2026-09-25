# EZScore_v1 — R10.4 bouton Import plus visible

Cette livraison corrige uniquement la mise en valeur du bouton Import dans le Répertoire.

## Objectif

Le bouton devait :
- attirer l’œil ;
- rester propre et professionnel ;
- afficher clairement :
  - l’action principale ;
  - le sous-texte fonctionnel.

## Résultat

Le bouton devient une vraie CTA visuelle :

```text
Nouvel import
Importer une chanson
MP3 + fiche chanson
```

avec :
- fond dégradé ;
- icône mise en avant ;
- meilleure hiérarchie visuelle ;
- meilleur espacement ;
- version CSS forcée pour éviter le cache.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_IMPORT_CTA_UI_R10_4.zip" -C H:\EZScore_v1

php bin\console cache:clear
```

Puis rechargement navigateur :

```text
Ctrl+F5
```

## Important

Cette livraison ne change :
- ni Doctrine ;
- ni les migrations ;
- ni le flux d’import.

Si l’import échoue avec :

```text
Le MP3 dépasse la taille maximale autorisée par PHP sur ce serveur.
```

alors le problème est la limite PHP d’upload, pas l’interface.
