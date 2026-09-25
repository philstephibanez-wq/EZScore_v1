# Recette ciblée R19 — Événements

La recette complète est à la racine dans `recette.md`.

Parcours minimal :

1. Éditeur A crée `Formation A`.
2. Il ajoute trois utilisateurs.
3. Il crée une playlist `Répétition 30/10/2026`.
4. Il crée l'événement `Répète du 30/10/2026`.
5. Il associe `Formation A`.
6. Les trois membres apparaissent comme participants sans invitation playlist.
7. Il associe la playlist.
8. La playlist est affectée au groupe si nécessaire et si les ACL le permettent.
9. Les membres voient la playlist et l'événement.
10. Un membre répond Présent, un autre Absent, un autre Peut-être.
11. Les réponses persistent.
12. Vérifier email si SMTP actif.
13. Vérifier WhatsApp/Facebook/X.
14. Créer un événement sans groupe, avec playlist partagée.
15. Le picker participants ne propose que les utilisateurs ayant déjà accès à cette playlist.
