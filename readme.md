# EZScore_v1 R6.5 — header type ChordU

R6.5 rapproche le header de la géométrie demandée à partir de la capture ChordU fournie.

## Disposition

```text
┌──────────────────────────────────────────────────────────────────────┐
│ ☰  EZSCORE v1      [ Rechercher un morceau ou un artiste ]  FR EN A │
└──────────────────────────────────────────────────────────────────────┘
```

- hamburger totalement à gauche ;
- `EZSCORE v1` immédiatement après ;
- champ de recherche horizontal centré/droite ;
- FR / EN ;
- badge utilisateur/avatar à droite ;
- header fixe ;
- drawer gauche off-canvas.

## Recherche

La recherche n'est pas factice : le formulaire envoie un `GET q=...` vers la route réelle `app_catalog`.

Le filtrage serveur du catalogue pourra être raccordé au paramètre `q` dans le prochain lot si nécessaire. Le header ne simule aucun résultat.

## Responsive

### Desktop
Header complet, champ de recherche large, badge utilisateur complet.

### Tablette
Champ réduit automatiquement ; badge utilisateur compact.

### Smartphone
FR/EN et texte du badge sont masqués si l'espace devient insuffisant ; le champ de recherche conserve la priorité.

## Contraintes

- aucun fallback ;
- aucune DATA modifiée ;
- aucune permission dans JavaScript ;
- drawer géré en JavaScript natif ;
- DATA reste la seule source de vérité métier ;
- aucune migration.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R6_5_CHORDU_HEADER.zip" -C H:\EZScore_v1

php bin\console cache:clear
```

Puis `Ctrl+F5`.
