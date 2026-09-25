# R24 — EZScore Analysis Worker Desktop

## Architecture

```text
EZScore Symfony
    ⇅ API authentifiée / heartbeat / commandes
EZScore Analysis Worker Desktop
    ⇅ subprocess + fichiers de progression
Python STEMS
```

La communication est bidirectionnelle et continue :

- EZScore fournit les jobs et peut transmettre des commandes via le canal de heartbeat ;
- le Worker publie son état, ses capacités, le job courant, sa progression, ses erreurs et sa fin de traitement.

R24 ne change pas le périmètre musical : **STEMS uniquement**.

## Runtime

Le Worker ne fait plus confiance à un chemin Python statique. Il teste les candidats et n'accepte qu'un Python capable d'importer :

```text
bs_roformer
mel_band_roformer
torch
```

avec `torch.cuda.is_available() == True`.

Le chemin historique connu est testé en priorité :

```text
H:\EZScore\.venv-py313\Scripts\python.exe
```

Cela corrige le défaut R23.4 où le worker permanent pouvait lancer `H:\EZScore_v1\.venv-py313` dépourvu de `bs_roformer`.

## Application

La fenêtre affiche :

- connexion EZScore ;
- Python STEM réellement utilisé ;
- GPU / CUDA ;
- état RX/TX ;
- chanson et job courants ;
- progression ;
- étape ;
- console temps réel ;
- démarrage ;
- pause/reprise ;
- annulation du job ;
- accès au navigateur EZScore ;
- accès aux logs.

## Launcher

`EZScore-Launcher.cmd` est le point d'entrée Windows.

Il :

1. désactive l'ancien worker planifié `EZScore STEM Worker` pour éviter deux consommateurs ;
2. crée `ANALYSIS_WORKER_TOKEN` dans `.env.local` s'il n'existe pas ;
3. démarre le serveur web EZScore s'il n'écoute pas déjà ;
4. démarre l'application desktop Worker ;
5. ouvre EZScore dans le navigateur.
