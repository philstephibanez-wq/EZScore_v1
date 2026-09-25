<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$twig = file_get_contents($root.'/templates/stems/index.html.twig');
$css = file_get_contents($root.'/public/assets/css/stems.css');
$js = file_get_contents($root.'/public/assets/js/stems-console.js');

$checks = [
    'native browser confirm removed' => !str_contains($twig, 'confirm('),
    'reanalyze form has modal hook' => str_contains($twig, 'data-stem-reanalyze-form'),
    'custom modal is present' => str_contains($twig, 'data-stem-reanalyze-modal'),
    'cancel action exists' => str_contains($twig, 'data-stem-reanalyze-cancel'),
    'confirm action exists' => str_contains($twig, 'data-stem-reanalyze-confirm'),
    'modal is app-styled' => str_contains($css, '.stem-modal-backdrop') && str_contains($css, '.stem-modal'),
    'JS intercepts reanalysis submit' => str_contains($js, "reanalyzeForm?.addEventListener('submit'"),
    'ESC closes modal' => str_contains($js, "event.key === 'Escape'"),
    'confirmed form is resubmitted' => str_contains($js, 'reanalyzeForm.requestSubmit()'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.7 confirmation-modal checks passed.\n");
