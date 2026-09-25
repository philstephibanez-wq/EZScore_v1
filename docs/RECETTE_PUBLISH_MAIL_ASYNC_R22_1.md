# Recette ciblée R22.1 — Mailing publication asynchrone

## Test local en deux terminaux

Terminal A :

```powershell
cd H:\EZScore_v1
php -S 127.0.0.1:8000 -t public
```

ou votre serveur Symfony habituel.

Terminal B :

```powershell
cd H:\EZScore_v1
powershell -ExecutionPolicy Bypass -File .\scripts\start_mailing_worker.ps1
```

## Scénario

1. Arrêter le worker.
2. Publier une chanson.
3. Vérifier que la publication répond sans attendre l'envoi des mails.
4. Vérifier une ligne non terminée dans `song_publication_mail_jobs`.
5. Vérifier qu'aucun mail n'est parti.
6. Lancer :

```powershell
php bin\console app:mailing:worker --once
```

7. Vérifier les mails.
8. Vérifier `completed_at`.
9. Publier une autre chanson avec le worker continu démarré.
10. Vérifier que le traitement part en arrière-plan.
11. Tester une panne SMTP : la chanson reste publiée, le job reçoit une erreur et `next_attempt_at`.
12. Rétablir SMTP et attendre le retry : seuls les destinataires non encore envoyés doivent repartir.
