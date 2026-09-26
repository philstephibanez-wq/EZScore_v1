<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');
$css = file_get_contents($root.'/public/assets/css/stems.css');

$checks = [
    'original route remains available' => str_contains($controller, "app_song_stems_original_audio"),
    'kernel project dir is injected' => str_contains($controller, "#[Autowire('%kernel.project_dir%')]"),
    'relative audio path is resolved from project root' => str_contains($controller, '$projectDir.DIRECTORY_SEPARATOR.$relativePath'),
    'resolved original path is checked' => str_contains($controller, 'if (!is_file($path))'),
    'path traversal protection remains' => str_contains($controller, 'str_starts_with($realPath, $projectPrefix)'),
    'BinaryFileResponse receives absolute real path' => str_contains($controller, 'new BinaryFileResponse($realPath)'),
    'original MIME type remains preserved' => str_contains($controller, 'getAudioMimeType()'),
    'drawer remains above STEMS backdrop' => str_contains($css, 'z-index:6002!important'),
    'STEMS content cannot intercept clicks while drawer is open' => str_contains($css, '.ez-shell.ez-drawer-open .ez-main') && str_contains($css, 'pointer-events:none'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R25.4 STEMS checks passed.\n");
