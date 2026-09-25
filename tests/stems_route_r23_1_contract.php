<?php

declare(strict_types=1);

$routes = file_get_contents(dirname(__DIR__).'/config/routes.yaml');

if (!is_string($routes) || !str_contains($routes, 'localized_song_stems:')) {
    fwrite(STDERR, "FAIL: localized_song_stems route import missing.\n");
    exit(1);
}

if (!str_contains($routes, '../src/Controller/SongStemController.php')) {
    fwrite(STDERR, "FAIL: SongStemController is not imported.\n");
    exit(1);
}

fwrite(STDOUT, "OK: SongStemController localized routes are imported.\n");
