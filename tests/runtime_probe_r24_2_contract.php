<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root.'/scripts/prepare_analysis_runtime.ps1');

$checks = [
    'uses Start-Process for native probe' => str_contains($script, 'Start-Process'),
    'captures stderr to file' => str_contains($script, 'RedirectStandardError'),
    'does not use fragile 2>$null import probe' => !str_contains($script, '2>$null'),
    'installs requirements after failed import' => str_contains($script, 'pip", "install", "-r"'),
    'rechecks imports after install' => substr_count($script, 'import bs_roformer,mel_band_roformer') >= 2,
    'prints Torch CUDA diagnostics' => str_contains($script, 'torch.cuda.is_available()'),
    'keeps EZScore_v1 local venv only' => str_contains($script, 'H:\EZScore_v1\.venv-py313'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.2 runtime-probe checks passed.\n");
