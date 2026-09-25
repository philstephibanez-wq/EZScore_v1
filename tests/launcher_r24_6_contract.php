<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$web = file_get_contents($root.'/scripts/start_ezscore_web.ps1');
$launcher = file_get_contents($root.'/scripts/launch_ezscore.ps1');
$app = file_get_contents($root.'/worker_app/ezscore_analysis_worker.pyw');
$vbs = file_get_contents($root.'/EZScore-Launcher.vbs');
$cmd = file_get_contents($root.'/EZScore-Launcher.cmd');

$checks = [
    'web defaults to 8501' => str_contains($web, '[int]$Port = 8501'),
    'web uses PHP built-in server' => str_contains($web, '"-S"') && str_contains($web, '"127.0.0.1:$Port"'),
    'web serves EZScore_v1 public dir' => str_contains($web, 'Join-Path $Project "public"'),
    'web process is hidden' => str_contains($web, '-WindowStyle Hidden'),
    'launcher defaults to 8501' => str_contains($launcher, '[int]$Port = 8501'),
    'desktop defaults to 8501' => str_contains($app, 'http://127.0.0.1:8501'),
    'desktop hides child process windows' => str_contains($app, 'CREATE_NO_WINDOW'),
    'desktop STEM process uses creationflags' => str_contains($app, 'creationflags=WINDOWS_NO_WINDOW'),
    'VBS launcher hides PowerShell' => str_contains($vbs, 'shell.Run cmd, 0, False'),
    'CMD delegates to VBS' => str_contains($cmd, 'wscript.exe'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.6 launcher checks passed.\n");
