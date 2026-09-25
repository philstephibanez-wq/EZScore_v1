# Recette ciblée R22 — Nouvelle chanson disponible

## Test minimal

1. Configurer un vrai `MAILER_DSN` et `MAILER_FROM`.
2. Utiliser deux comptes actifs et vérifiés.
3. Dans Profil, laisser l'option « nouvelle chanson » activée sur le premier.
4. La désactiver sur le second.
5. Publier un morceau non publié.
6. Vérifier que seul le premier reçoit le message.
7. Vérifier la ligne `song_publication_notifications`.
8. Dépublier puis republier.
9. Vérifier qu'un destinataire déjà marqué `sent_at` ne reçoit pas de doublon.
10. Provoquer volontairement un échec SMTP sur un compte de test et vérifier `failed_at` + `last_error`.
