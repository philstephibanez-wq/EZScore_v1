<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$web = file_get_contents($root.'/scripts/start_ezscore_web.ps1');
$splash = file_get_contents($root.'/scripts/launch_ezscore_splash.ps1');
$backend = file_get_contents($root.'/scripts/launch_ezscore_backend.ps1');

$checks = [
    'recognizes existing EZScore PHP server by command line' => str_contains($web, 'Test-EZScorePhpServer'),
    'checks exact bind' => str_contains($web, '-s 127.0.0.1:$Port'),
    'checks exact public document root' => str_contains($web, '$ExpectedDocRoot'),
    'persists recognized existing PID' => str_contains($web, '$ExistingPid | Set-Content'),
    'reuses matching server' => str_contains($web, 'Serveur EZScore existant reconnu et reutilise'),
    'rejects wrong server' => str_contains($web, "ce n'est pas le serveur EZScore attendu"),
    'splash remains WinForms' => str_contains($splash, 'System.Windows.Forms.Form'),
    'splash keeps progress bar' => str_contains($splash, 'System.Windows.Forms.ProgressBar'),
    'backend still opens public URL' => str_contains($backend, 'https://ezscore.logandplay.com/'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}
fwrite(STDOUT, "\n".count($checks)." R24.15 launcher checks passed.\n");
