<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'shared partial' => is_file($root.'/templates/layout/song/_workflow_tabs.html.twig'),
    'import tab' => str_contains(file_get_contents($root.'/templates/song/import.html.twig'), "current:'import'"),
    'edit tab' => str_contains(file_get_contents($root.'/templates/song/edit.html.twig'), "current:'edit'"),
    'stems tab' => str_contains(file_get_contents($root.'/templates/stems/index.html.twig'), "current:'stems'"),
    'import disabled stems' => str_contains(file_get_contents($root.'/templates/layout/song/_workflow_tabs.html.twig'), 'workflow-tab disabled'),
    'existing modifier route' => str_contains(file_get_contents($root.'/templates/layout/song/_workflow_tabs.html.twig'), "app_song_edit"),
    'existing stems route' => str_contains(file_get_contents($root.'/templates/layout/song/_workflow_tabs.html.twig'), "app_song_stems"),
];

foreach ($checks as $label => $ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    fwrite(STDOUT, "OK: $label\n");
}
fwrite(STDOUT, "\n".count($checks)." R23.2 workflow checks passed.\n");
