<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$splash = file_get_contents($root.'/scripts/launch_ezscore_splash.ps1');
$cmd = file_get_contents($root.'/EZScore-Launcher.cmd');
$debug = file_get_contents($root.'/EZScore-Launcher-Debug.cmd');

$checks = [
    'normal launcher has no VBS indirection' => !str_contains($cmd, 'wscript.exe'),
    'normal launcher directly starts PowerShell' => str_contains($cmd, 'powershell.exe'),
    'PowerShell launcher is STA' => str_contains($cmd, '-STA'),
    'PowerShell console is hidden' => str_contains($cmd, '-WindowStyle Hidden'),
    'splash is native WinForms' => str_contains($splash, 'System.Windows.Forms.Form'),
    'splash has progress bar' => str_contains($splash, 'System.Windows.Forms.ProgressBar'),
    'splash is topmost while starting' => str_contains($splash, '$form.TopMost = $true'),
    'splash logs startup' => str_contains($splash, 'ezscore-launcher.log'),
    'splash logs shown state' => str_contains($splash, 'Splash shown.'),
    'fatal error has visible MessageBox' => str_contains($splash, 'System.Windows.Forms.MessageBox'),
    'debug launcher exists' => str_contains($debug, 'launch_ezscore_splash.ps1'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.14 launcher checks passed.\n");
