<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');

$checks = [
    'playback storage service import exists' =>
        str_contains($controller, 'use App\\Service\\SongStemPlaybackStorage;'),
    'show action type-hints imported service' =>
        str_contains($controller, 'SongStemPlaybackStorage $playback'),
    'playback audio action type-hints imported service' =>
        substr_count($controller, 'SongStemPlaybackStorage $playback') >= 2,
    'controller does not resolve playback service in App\\Controller namespace' =>
        !str_contains($controller, 'App\\Controller\\SongStemPlaybackStorage'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R27.1 namespace checks passed.\n");
