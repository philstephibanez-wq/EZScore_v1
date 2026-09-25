<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$splash = file_get_contents($root.'/scripts/launch_ezscore_splash.ps1');
$backend = file_get_contents($root.'/scripts/launch_ezscore_backend.ps1');
$web = file_get_contents($root.'/scripts/start_ezscore_web.ps1');
$vbs = file_get_contents($root.'/EZScore-Launcher.vbs');
$cmd = file_get_contents($root.'/EZScore-Launcher.cmd');

$checks = [
    'HTA removed' => !file_exists($root.'/EZScore-Launcher.hta'),
    'native WinForms splash exists' => str_contains($splash, 'System.Windows.Forms.Form'),
    'splash has progress bar' => str_contains($splash, 'System.Windows.Forms.ProgressBar'),
    'splash starts backend after shown' => str_contains($splash, '$form.Add_Shown'),
    'backend runs hidden' => str_contains($splash, '-WindowStyle Hidden'),
    'status polling every 250ms' => str_contains($splash, '$timer.Interval = 250'),
    'local server command is displayed' => str_contains($splash, 'php -S 127.0.0.1:8501 -t'),
    'public URL is displayed' => str_contains($splash, 'https://ezscore.logandplay.com/'),
    'backend opens public URL' => str_contains($backend, 'Start-Process $BrowserUrl'),
    'worker stays on local API' => str_contains($backend, '$env:EZSCORE_WORKER_URL = $LocalBaseUrl'),
    'web server binds 8501' => str_contains($web, '[int]$Port = 8501'),
    'CMD delegates to VBS' => str_contains($cmd, 'wscript.exe'),
    'VBS starts PowerShell in STA mode' => str_contains($vbs, '-STA'),
    'no mshta remains' => !str_contains($cmd.$vbs.$splash, 'mshta'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.13 launcher checks passed.\n");
