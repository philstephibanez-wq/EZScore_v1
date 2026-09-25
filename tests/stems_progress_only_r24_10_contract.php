<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$twig = file_get_contents($root.'/templates/stems/index.html.twig');
$js = file_get_contents($root.'/public/assets/js/stems-progress.js');
$css = file_get_contents($root.'/public/assets/css/stems.css');

$checks = [
    'web Python console removed' => !str_contains($twig, 'Console STEMS'),
    'web log download removed' => !str_contains($twig, 'Télécharger le log'),
    'no log content element exposed' => !str_contains($twig, 'data-stem-log-console'),
    'progress status URL only exists while active' => str_contains($twig, 'data-stem-status-url'),
    'progress percentage remains visible' => str_contains($twig, 'data-stem-progress-percent'),
    'progress engine metadata remains visible' => str_contains($twig, 'engine_percent'),
    'progress script replaces console script' => str_contains($twig, 'stems-progress.js'),
    'polling remains two seconds' => str_contains($js, 'window.setInterval(refresh, 2000)'),
    'polling stops at terminal state' => str_contains($js, 'stopPolling()'),
    'terminal reload is guarded once' => str_contains($js, 'terminalHandled'),
    'only one reload after terminal state' => substr_count($js, 'window.location.reload()') === 1,
    'browser confirm remains replaced by app modal' => !str_contains($twig, 'confirm(') && str_contains($twig, 'data-stem-reanalyze-modal'),
    'console CSS removed' => !str_contains($css, '.stem-console'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.10 progress-only checks passed.\n");
