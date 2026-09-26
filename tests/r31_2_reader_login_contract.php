<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$patcher = file_get_contents($root.'/scripts/apply_r31_2_reader_login_landing_fix.py');

$checks = [
    'forces default target after form login' => str_contains($patcher, 'always_use_default_target_path: true'),
    'default target remains catalog' => str_contains($patcher, 'default_target_path: app_catalog'),
    'does not change role hierarchy' => !str_contains($patcher, 'ROLE_READER: ROLE_EDITOR'),
    'no Doctrine changes' => !str_contains($patcher, 'migration'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}
fwrite(STDOUT, "\n".count($checks)." R31.2 reader-login checks passed.\n");
