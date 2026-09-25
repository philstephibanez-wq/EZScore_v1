<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$web = file_get_contents($root.'/scripts/start_ezscore_web.ps1');
$backend = file_get_contents($root.'/scripts/launch_ezscore_backend.ps1');
$hta = file_get_contents($root.'/EZScore-Launcher.hta');
$cmd = file_get_contents($root.'/EZScore-Launcher.cmd');

$checks = [
    'local server defaults 8501' => str_contains($web, '[int]$Port = 8501'),
    'local server exact bind' => str_contains($web, '"127.0.0.1:$Port"'),
    'local server serves project public dir' => str_contains($web, '$PublicDir'),
    'browser URL defaults to public EZScore' => str_contains($backend, '"https://ezscore.logandplay.com/"'),
    'browser URL remains configurable' => str_contains($backend, 'EZSCORE_BROWSER_URL'),
    'worker stays on local API URL' => str_contains($backend, '$env:EZSCORE_WORKER_URL = $LocalBaseUrl'),
    'browser opens public URL not health URL' => str_contains($backend, 'Start-Process $BrowserUrl'),
    'health check remains local' => str_contains($backend, '$HealthUrl = "$LocalBaseUrl/fr/login"'),
    'splash screen exists' => str_contains($hta, '<HTA:APPLICATION'),
    'splash has progress bar' => str_contains($hta, 'id="bar"'),
    'splash shows public URL' => str_contains($hta, 'https://ezscore.logandplay.com/'),
    'CMD opens splash directly' => str_contains($cmd, 'mshta.exe'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.12 launcher checks passed.\n");
