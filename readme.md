# EZScore_v1 — R24.16 Notification Worker hors ligne

EZScore doit signaler clairement quand l'application `EZScore Analysis Worker` n'est plus active.

## Détection

Le Worker envoie déjà un heartbeat environ toutes les 2 secondes.

R24.16 considère le Worker hors ligne si aucun heartbeat valide n'a été reçu depuis plus de :

```text
8 secondes
```

Cela évite un faux positif sur un simple retard de heartbeat.

## Notification globale

Pour les rôles `EDITOR` et `ADMIN`, EZScore affiche une bannière persistante :

```text
Moteur d'analyse hors ligne
Les analyses sont indisponibles tant que EZScore Analysis Worker n'est pas démarré.
```

La bannière est vérifiée toutes les 5 secondes.

Elle disparaît automatiquement lorsque le Worker recommence à envoyer ses heartbeats.

Les lecteurs ne voient pas cette alerte puisqu'ils ne lancent pas d'analyses.

## Sécurité fonctionnelle

La notification n'est pas seulement visuelle.

La route qui crée un job STEMS refuse maintenant de mettre un job en file si le Worker est hors ligne :

```text
Le moteur d'analyse est hors ligne.
Démarrez EZScore Analysis Worker avant de lancer une analyse.
```

Cela évite d'accumuler des jobs impossibles à traiter.

## Endpoint

Nouveau endpoint authentifié :

```text
GET /analysis/worker/status
```

Réponse minimale :

```json
{
  "online": false,
  "status": "offline",
  "last_seen_at": "...",
  "last_seen_age_seconds": 12
}
```

Aucune capacité GPU, aucun chemin local, aucun token et aucune information technique sensible ne sont exposés.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R24_16_WORKER_OFFLINE_NOTIFICATION.zip" -C H:\EZScore_v1

Get-ChildItem src,tests -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}

php tests\analysis_worker_offline_r24_16_contract.php
php bin\console lint:yaml config translations
php bin\console lint:twig templates
php bin\console cache:clear
php bin\console debug:router | Select-String "analysis_worker_status"
```

Aucune migration Doctrine.
Aucun changement du moteur STEMS.
