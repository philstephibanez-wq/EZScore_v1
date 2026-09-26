# EZScore_v1 — R31.2 Reader login landing fix

Le mail d'activation fonctionne désormais et la connexion d'Aline aboutit bien à une session authentifiée.

Le `403 ROLE_EDITOR` observé après connexion signifie que Symfony a ensuite tenté de renvoyer Aline vers une page protégée Éditeur.

La cause est le comportement standard de `form_login` : Symfony peut réutiliser un `target_path` mémorisé dans la session avant l'authentification.

Pour un Lecteur, ce target peut être une page Éditeur et provoquer immédiatement :

```text
Access Denied. The user doesn't have ROLE_EDITOR.
```

R31.2 force donc la connexion locale à toujours arriver sur le Répertoire, qui est l'écran valide pour les Lecteurs.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R31_2_READER_LOGIN_LANDING_FIX.zip" -C H:\EZScore_v1

H:\EZScore_v1\.venv-py313\Scripts\python.exe .\scripts\apply_r31_2_reader_login_landing_fix.py

php .\tests\r31_2_reader_login_contract.php
php bin\console lint:yaml config
php bin\console cache:clear
```

Attendu :

```text
4 R31.2 reader-login checks passed.
```

Puis :
1. se déconnecter ;
2. se reconnecter avec Aline ;
3. vérifier l'arrivée sur le Répertoire ;
4. vérifier qu'aucune page Éditeur n'est ouverte automatiquement.

Aucune migration Doctrine.
Aucune modification des rôles.
Aucune modification ChordsLab / STEMS / Worker.
