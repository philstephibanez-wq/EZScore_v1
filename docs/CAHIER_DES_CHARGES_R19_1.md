# EZScore_v1 — Complément au cahier des charges R19.1

## Vocabulaire produit / modèle technique

Le modèle technique reste générique :

```text
Event
EventParticipant
events
event_participants
```

L'interface utilisateur expose actuellement un seul type métier :

```text
Session
```

Une **Session** est donc un `Event` dont :

```text
type = session
```

Cette séparation est volontaire.

Elle permet d'ajouter plus tard d'autres types sans remettre en cause le modèle événementiel, par exemple :

```text
EventType
- session        # implémenté
- concert        # futur éventuel
- audition       # futur éventuel
- workshop       # futur éventuel
- meeting        # futur éventuel
```

Les types futurs ne sont pas activés tant qu'ils ne sont pas spécifiés et implémentés.

## Règle UI

Dans R19.1 :

- menu FR : `Sessions`
- titre FR : `Sessions`
- création : `Nouvelle session`
- emails et messages : vocabulaire `session`
- anglais : `Session / Sessions`

Le mot `Event` reste réservé au code, aux entités et aux tables.
