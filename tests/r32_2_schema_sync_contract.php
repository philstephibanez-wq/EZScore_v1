<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$patcher = file_get_contents($root.'/scripts/apply_r32_2_schema_default_sync.py');

$checks = [
    'targets Song entity' => str_contains($patcher, '"Song" / "Song.php"'),
    'targets chord analysis level only' => str_contains($patcher, "chord_analysis_level"),
    'adds Doctrine default' => str_contains($patcher, "options: ['default' => 'intermediate']"),
    'does not mutate database' => !str_contains($patcher, 'schema:update --force'),
    'creates backup' => str_contains($patcher, 'shutil.copy2(SONG, backup)'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R32.2 schema-sync checks passed.\n");
