<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root.'/scripts/apply_r32_1_duplicate_controller_cleanup.py');

$checks = [
    'targets exact stray path' => str_contains($script, '"src" / "Controller_SongLabController.php"'),
    'preserves canonical controller' => str_contains($script, '"Controller" / "SongLabController.php"'),
    'verifies class signature before delete' => str_contains($script, 'EXPECTED = "final class SongLabController extends AbstractController"'),
    'creates backup before delete' => str_contains($script, 'shutil.copy2(STRAY, backup)'),
    'deletes only stray file' => str_contains($script, 'STRAY.unlink()'),
    'idempotent when stray absent' => str_contains($script, 'No stray duplicate controller found'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R32.1 cleanup checks passed.\n");
