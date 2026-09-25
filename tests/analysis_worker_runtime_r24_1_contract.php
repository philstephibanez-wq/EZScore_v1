<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$finder = file_get_contents($root.'/scripts/find_analysis_python.ps1');
$prepare = file_get_contents($root.'/scripts/prepare_analysis_runtime.ps1');
$launcher = file_get_contents($root.'/scripts/launch_ezscore.ps1');
$app = file_get_contents($root.'/worker_app/ezscore_analysis_worker.pyw');

$checks = [
    'finder uses EZScore_v1 local venv' => str_contains($finder, '.venv-py313\Scripts\python.exe'),
    'finder has no old EZScore path' => !str_contains($finder, 'H:\EZScore\.venv-py313'),
    'desktop has no old EZScore path' => !str_contains($app, 'H:\\EZScore\\.venv-py313'),
    'prepare creates local venv' => str_contains($prepare, 'py -3.13 -m venv'),
    'prepare installs local requirements' => str_contains($prepare, '-m pip install -r'),
    'prepare validates CUDA' => str_contains($prepare, 'torch.cuda.is_available()'),
    'launcher prepares local runtime' => str_contains($launcher, 'prepare_analysis_runtime.ps1'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.1 runtime checks passed.\n");
