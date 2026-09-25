<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$prepare = file_get_contents($root.'/scripts/prepare_analysis_runtime.ps1');
$finder = file_get_contents($root.'/scripts/find_analysis_python.ps1');
$starter = file_get_contents($root.'/scripts/start_analysis_worker_desktop.ps1');
$launcher = file_get_contents($root.'/scripts/launch_ezscore.ps1');
$app = file_get_contents($root.'/worker_app/ezscore_analysis_worker.pyw');

$checks = [
    'runtime probes use temporary py file' => str_contains($prepare, 'ezscore-probe-'),
    'runtime probe no fragile python -c' => !str_contains($prepare, '-c "import'),
    'runtime only references EZScore_v1' => !str_contains($prepare, 'H:\EZScore\.venv-py313'),
    'finder only uses local venv' => !str_contains($finder, 'H:\EZScore\.venv-py313'),
    'desktop app has no old EZScore fallback' => !str_contains($app, 'H:\\EZScore\\.venv-py313'),
    'desktop app is native Tk window' => str_contains($app, 'self.root = tk.Tk()'),
    'starter launches pythonw' => str_contains($starter, 'pythonw.exe'),
    'starter validates launched process' => str_contains($starter, '$Process.HasExited'),
    'launcher prepares runtime' => str_contains($launcher, 'prepare_analysis_runtime.ps1'),
    'launcher launches desktop' => str_contains($launcher, 'start_analysis_worker_desktop.ps1'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.3 desktop-launch checks passed.\n");
