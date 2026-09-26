<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$p = file_get_contents($root.'/scripts/apply_r32_3_ui_i18n_fix.py');

$checks = [
    'workflow CSS global' => str_contains($p, 'workflow-tabs-r23-2.css?v=20260926r32_3'),
    'auto time signature retained' => str_contains($p, "'auto',"),
    'explicit ChordsLab translation domain' => str_contains($p, "'chordslab'"),
    'dynamic analysis level domain' => str_contains($p, "('chordslab.level.' ~ level)|trans({}, 'chordslab')"),
    'ChordsLab flash domain' => str_contains($p, "message starts with 'chordslab.'"),
    'transport icon duplication removed' => str_contains($p, 'Avoid duplicated icons'),
    'backup before modifications' => str_contains($p, 'shutil.copy2(path, dst)'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}
echo "\n".count($checks)." R32.3 UI/i18n checks passed.\n";
