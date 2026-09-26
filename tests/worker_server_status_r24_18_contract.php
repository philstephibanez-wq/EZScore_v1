<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$app = file_get_contents($root.'/worker_app/ezscore_analysis_worker.pyw');

$checks = [
    'worker version bumped' => str_contains($app, 'APP_VERSION = "R24.18"'),
    'local server monitor exists' => str_contains($app, 'class LocalServerMonitor'),
    'server is probed on 8501' => str_contains($app, 'http://127.0.0.1:8501'),
    'server health probes login route' => str_contains($app, '"/fr/login"'),
    'server PID file is read' => str_contains($app, 'ezscore-web.pid'),
    'server status row exists' => str_contains($app, '("Serveur local", self.server_var)'),
    'active status is shown' => str_contains($app, 'ACTIF · 127.0.0.1:8501'),
    'stopped status is shown' => str_contains($app, 'ARRÊTÉ · 127.0.0.1:8501'),
    'monitor refreshes every 2 seconds' => str_contains($app, 'self.stop_event.wait(2.0)'),
    'monitor stops on close' => str_contains($app, 'self.server_monitor.stop()'),
    'open EZScore uses public URL' => str_contains($app, 'browser_url(project_root()) + "/fr/catalog"'),
    'public URL defaults to logandplay' => str_contains($app, 'https://ezscore.logandplay.com'),
    'local API label is explicit' => str_contains($app, '("API EZScore", self.url_var)'),
    'no old EZScore venv fallback' => !str_contains($app, 'H:\\EZScore\\.venv-py313'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R24.18 worker-server-status checks passed.\n");
