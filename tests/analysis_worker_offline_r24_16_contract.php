<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$store = file_get_contents($root.'/src/Service/AnalysisDesktopStateStore.php');
$statusController = file_get_contents($root.'/src/Controller/AnalysisWorkerStatusController.php');
$stemController = file_get_contents($root.'/src/Controller/SongStemController.php');
$base = file_get_contents($root.'/templates/base.html.twig');
$js = file_get_contents($root.'/public/assets/js/analysis-worker-status.js');
$css = file_get_contents($root.'/public/assets/css/analysis-worker-status.css');
$routes = file_get_contents($root.'/config/routes.yaml');

$checks = [
    'heartbeat timeout is explicit' => str_contains($store, 'ONLINE_AFTER_SECONDS = 8'),
    'state store exposes isOnline' => str_contains($store, 'function isOnline'),
    'public status does not expose capabilities' => str_contains($store, 'publicStatus') && !str_contains($statusController, 'capabilities'),
    'status endpoint exists' => str_contains($statusController, "/analysis/worker/status"),
    'status endpoint requires editor' => str_contains($statusController, "denyAccessUnlessGranted('ROLE_EDITOR')"),
    'global editor banner exists' => str_contains($base, 'data-analysis-worker-banner'),
    'banner text is translated' => str_contains($base, 'analysis_worker.offline.title'),
    'status javascript polls endpoint' => str_contains($js, 'window.setInterval(refresh, 5000)'),
    'network failure is treated offline' => str_contains($js, 'setOffline(true)'),
    'offline banner has dedicated styling' => str_contains($css, '.analysis-worker-banner'),
    'stem generation is blocked server-side' => str_contains($stemController, 'if (!$workerState->isOnline())'),
    'offline flash translation exists' => str_contains($stemController, 'stems.error.worker_offline'),
    'status controller is routed' => str_contains($routes, 'analysis_worker_status_controller'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.16 worker-offline checks passed.\n");
