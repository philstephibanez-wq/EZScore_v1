<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$ui = file_get_contents($root.'/scripts/launch_ezscore_ui.ps1');
$vbs = file_get_contents($root.'/EZScore-Launcher.vbs');

$checks = [
    'launcher native form remains visible' => str_contains($ui, 'System.Windows.Forms.Form'),
    'startup log is visible immediately' => str_contains($ui, 'Launcher visible.'),
    'runtime prepare is not repeated on every launch' => !str_contains($ui, 'prepare_analysis_runtime.ps1'),
    'web server starts before worker' => strpos($ui, 'start_ezscore_web.ps1') < strpos($ui, 'start_analysis_worker_desktop.ps1'),
    'worker launches immediately after web readiness' => str_contains($ui, 'Ouverture de EZScore Analysis Worker'),
    'worker owns Python CUDA verification' => str_contains($ui, 'vérification Python/CUDA dans sa fenêtre'),
    'timer uses script scope' => str_contains($ui, '$script:closeTimer'),
    'timer callback guards null' => str_contains($ui, 'if ($null -ne $script:closeTimer)'),
    'form callback guards null' => str_contains($ui, 'if ($null -ne $form -and -not $form.IsDisposed)'),
    'failure keeps launcher visible' => str_contains($ui, 'Fail-Step'),
    'VBS keeps PowerShell console hidden' => str_contains($vbs, 'shell.Run cmd, 0, False'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.9 launcher checks passed.\n");
