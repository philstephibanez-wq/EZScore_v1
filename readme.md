# EZScore_v1 — R24.15 Reuse du serveur PHP deja actif

Le splash R24.14 affichait une erreur si le port 8501 etait deja occupe, meme lorsque le processus etait justement le bon serveur EZScore.

R24.15 inspecte maintenant la ligne de commande du processus qui ecoute sur 8501.

Si elle correspond a :

```powershell
php -S 127.0.0.1:8501 -t H:\EZScore_v1\public
```

le launcher :

1. reconnait le serveur comme valide ;
2. memorise son PID ;
3. le reutilise ;
4. poursuit vers Analysis Worker puis le navigateur.

Si 8501 est utilise par un autre processus ou par un serveur PHP avec un autre document root, le launcher conserve une erreur explicite.

Les scripts PowerShell sont egalement ecrits avec BOM UTF-8, et les messages visibles utilisent des caracteres simples pour eviter les textes corrompus de type `DÃ©marrage`.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_15_REUSE_EXISTING_PHP_SERVER.zip" -C H:\EZScore_v1

php tests\launcher_r24_15_contract.php
```

Puis :

```powershell
.\EZScore-Launcher.cmd
```

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
