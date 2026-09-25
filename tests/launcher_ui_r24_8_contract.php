<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$ui = file_get_contents($root.'/scripts/launch_ezscore_ui.ps1');
$vbs = file_get_contents($root.'/EZScore-Launcher.vbs');
$cmd = file_get_contents($root.'/EZScore-Launcher.cmd');

$checks = [
    'launcher has a native visible form' => str_contains($ui, 'System.Windows.Forms.Form'),
    'launcher shows status text' => str_contains($ui, '$status.Text'),
    'launcher has progress bar' => str_contains($ui, 'System.Windows.Forms.ProgressBar'),
    'launcher has startup log' => str_contains($ui, 'System.Windows.Forms.TextBox'),
    'launcher reports Python CUDA stage' => str_contains($ui, 'Vérification Python / RoFormer / CUDA'),
    'launcher reports web server stage' => str_contains($ui, 'Démarrage du serveur EZScore'),
    'launcher waits for web readiness' => str_contains($ui, 'Attente du serveur web'),
    'launcher reports desktop worker stage' => str_contains($ui, 'Démarrage de EZScore Analysis Worker'),
    'launcher opens browser after readiness' => str_contains($ui, 'Start-Process $Url'),
    'launcher keeps failure window visible' => str_contains($ui, 'Fail-Step'),
    'launcher closes after success' => str_contains($ui, '$timer.Interval = 1600'),
    'VBS launches PowerShell hidden but STA' => str_contains($vbs, '-STA') && str_contains($vbs, 'shell.Run cmd, 0, False'),
    'CMD immediately delegates to VBS' => str_contains($cmd, 'wscript.exe'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.8 launcher-UI checks passed.\n");
