<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = file_get_contents($root.'/scripts/prepare_analysis_runtime.ps1');

$checks = [
    'uses EZScore_v1 local venv' => str_contains($script, 'H:\EZScore_v1\.venv-py313'),
    'detects CUDA state' => str_contains($script, 'torch.cuda.is_available()'),
    'repairs CPU-only torch' => str_contains($script, 'CPU-only Torch detected'),
    'installs CUDA torch from dedicated index' => str_contains($script, 'download.pytorch.org/whl/cu132'),
    'forces replacement of CPU torch' => str_contains($script, '--force-reinstall'),
    'installs torch vision and audio together' => str_contains($script, '"torch", "torchvision", "torchaudio"'),
    'rechecks CUDA after install' => str_contains($script, 'Rechecking Torch / CUDA'),
    'final stem import validation exists' => str_contains($script, 'STEM_RUNTIME_OK'),
    'no old EZScore python path' => !str_contains($script, 'H:\EZScore\.venv-py313'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.4 CUDA-runtime checks passed.\n");
