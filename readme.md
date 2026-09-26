# EZScore_v1 — R27.1 namespace fix

R27 avait bien créé :

```text
App\Service\SongStemPlaybackStorage
```

mais `SongStemController.php` utilisait le type :

```php
SongStemPlaybackStorage $playback
```

sans importer la classe.

PHP l'interprétait donc comme :

```text
App\Controller\SongStemPlaybackStorage
```

ce qui provoquait l'erreur Symfony :

```text
Cannot determine controller argument ...
non-existent class or interface:
App\Controller\SongStemPlaybackStorage
```

R27.1 ajoute uniquement l'import manquant :

```php
use App\Service\SongStemPlaybackStorage;
```

Aucune migration Doctrine.
Aucune modification du moteur STEMS.
Aucune modification AudioEngine.

## Installation

```powershell
cd H:\EZScore_v1

tar -xf "$env:USERPROFILE\Downloads\EZScore_v1_R27_1_PLAYBACK_NAMESPACE_FIX.zip" -C H:\EZScore_v1

php -l .\src\Controller\SongStemController.php
php .\tests\opus_playback_r27_1_namespace_contract.php

php bin\console cache:clear
```

Attendu :

```text
4 R27.1 namespace checks passed.
```

Puis recharger la page STEMS.
