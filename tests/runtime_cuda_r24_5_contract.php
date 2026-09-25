<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$s = file_get_contents($root.'/scripts/prepare_analysis_runtime.ps1');

$checks = [
    'local EZScore_v1 venv' => str_contains($s, 'H:\EZScore_v1\.venv-py313'),
    'CUDA torch index' => str_contains($s, 'download.pytorch.org/whl/cu132'),
    'force replaces CPU torch' => str_contains($s, '--force-reinstall'),
    'installs torch' => str_contains($s, '"torch",'),
    'does not request torchaudio' => !str_contains($s, '"torchaudio"'),
    'does not request torchvision' => !str_contains($s, '"torchvision"'),
    'rechecks CUDA' => str_contains($s, 'Rechecking Torch / CUDA'),
    'final RoFormer imports' => str_contains($s, 'import bs_roformer') && str_contains($s, 'import mel_band_roformer'),
    'final runtime marker' => str_contains($s, 'STEM_RUNTIME_OK'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.5 CUDA checks passed.\n");
