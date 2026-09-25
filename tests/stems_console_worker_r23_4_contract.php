<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function check_r234(bool $ok, string $label): void
{
    global $checks;
    ++$checks;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

$controller = file_get_contents($root.'/src/Controller/SongStemController.php');
$storage = file_get_contents($root.'/src/Service/SongStemStorage.php');
$template = file_get_contents($root.'/templates/stems/index.html.twig');
$js = file_get_contents($root.'/public/assets/js/stems-console.js');
$worker = file_get_contents($root.'/scripts/run_stem_worker_permanent.ps1');
$installer = file_get_contents($root.'/scripts/install_stem_worker_task.ps1');
$python = file_get_contents($root.'/analysis/stems_only.py');

check_r234(str_contains($controller, "name: 'app_song_stems_log'"), 'live log JSON route exists');
check_r234(str_contains($controller, "name: 'app_song_stems_log_download'"), 'log download route exists');
check_r234(str_contains($controller, "'job_status'"), 'log endpoint exposes current job status');
check_r234(str_contains($storage, 'readLogTail'), 'storage can tail worker log without loading whole file');
check_r234(str_contains($template, 'data-stem-log-console'), 'STEM page contains diagnostic console');
check_r234(str_contains($template, 'data-stem-progress-message'), 'main progress is live-update capable');
check_r234(!str_contains($template, 'http-equiv="refresh"'), 'whole-page meta refresh removed');
check_r234(str_contains($js, 'setInterval(refresh, 2000)'), 'console polls every two seconds');
check_r234(str_contains($js, 'renderMainProgress'), 'poll updates main progress as well as console');
check_r234(str_contains($worker, 'while ($true)'), 'permanent worker supervisor restarts continuously');
check_r234(str_contains($worker, 'app:stems:worker --sleep=2'), 'permanent supervisor runs asynchronous STEM worker');
check_r234(str_contains($installer, 'New-ScheduledTaskTrigger -AtLogOn'), 'worker scheduled task starts automatically at Windows logon');
check_r234(str_contains($installer, 'RestartCount 999'), 'scheduled task is configured to restart after failure');
check_r234(str_contains($python, 'def _run_tracked('), 'R23.3 tracked Python progress remains included');
check_r234(str_contains($python, 'Heartbeat every 2 s'), 'Python heartbeat remains included');

fwrite(STDOUT, "\n{$checks} R23.4 console/permanent-worker checks passed.\n");
