<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r24_check(bool $ok, string $label): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

$controller = file_get_contents($root.'/src/Controller/AnalysisDesktopController.php');
$state = file_get_contents($root.'/src/Service/AnalysisDesktopStateStore.php');
$routes = file_get_contents($root.'/config/routes.yaml');
$app = file_get_contents($root.'/worker_app/ezscore_analysis_worker.pyw');
$launcher = file_get_contents($root.'/scripts/launch_ezscore.ps1');
$finder = file_get_contents($root.'/scripts/find_analysis_python.ps1');

r24_check(str_contains($routes, 'analysis_desktop_controller:'), 'desktop API controller is routed');
r24_check(str_contains($controller, "#[Route('/hello'"), 'HELLO endpoint exists');
r24_check(str_contains($controller, "#[Route('/heartbeat'"), 'heartbeat endpoint exists');
r24_check(str_contains($controller, "/jobs/claim"), 'desktop claims STEM jobs from EZScore');
r24_check(str_contains($controller, "/jobs/{id}/progress"), 'desktop reports progress to EZScore');
r24_check(str_contains($controller, "/jobs/{id}/complete"), 'desktop completes jobs in EZScore');
r24_check(str_contains($controller, "/commands/{id}/ack"), 'server command acknowledgement exists');
r24_check(str_contains($state, 'pendingCommands'), 'server-to-worker command queue exists');
r24_check(str_contains($app, 'class WorkerWindow'), 'desktop UI exists');
r24_check(str_contains($app, 'tk.Tk()'), 'desktop UI uses native Tk window');
r24_check(str_contains($app, 'EZScore Analysis Worker'), 'desktop window is branded');
r24_check(str_contains($app, 'bs_roformer,mel_band_roformer'), 'runtime probes required stem modules');
r24_check(str_contains($app, 'torch.cuda.is_available'), 'runtime probes CUDA');
r24_check(str_contains($app, 'heartbeat'), 'desktop maintains continuous heartbeat');
r24_check(str_contains($app, 'cancel_current'), 'desktop can cancel current Python process');
r24_check(str_contains($launcher, 'start_ezscore_web.ps1'), 'launcher starts EZScore web server');
r24_check(str_contains($launcher, 'start_analysis_worker_desktop.ps1'), 'launcher starts backend desktop app');
r24_check(str_contains($launcher, 'EZScore STEM Worker'), 'launcher disables legacy R23 worker');
r24_check(str_contains($finder, 'H:\EZScore\.venv-py313\Scripts\python.exe'), 'known good old EZScore Python is preferred');
r24_check(str_contains($finder, 'bs_roformer,mel_band_roformer,torch'), 'Python candidate is capability-tested');

fwrite(STDOUT, "\n{$checks} R24 desktop-worker checks passed.\n");
