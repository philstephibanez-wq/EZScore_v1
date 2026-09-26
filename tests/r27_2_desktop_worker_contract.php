<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$patcher = file_get_contents($root.'/scripts/apply_r27_2_worker_proxy_fix.py');
$proxy = file_get_contents($root.'/analysis/build_playback_proxies.py');
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');

$checks = [
    'desktop worker patch targets direct completion path' =>
        str_contains($patcher, 'self.api.post(f"/internal/analysis/desktop/jobs/{job_id}/complete", {})'),
    'desktop worker launches proxy builder before complete' =>
        str_contains($patcher, 'build_playback_proxies.py') &&
        str_contains($patcher, 'Génération des proxies Opus 192 kb/s lancée.'),
    'desktop worker uses same source path from claimed job' =>
        str_contains($patcher, '--source", str(paths["source"])'),
    'desktop worker uses same storage root from claimed job' =>
        str_contains($patcher, '--storage-root", str(paths["storage_root"])'),
    'desktop worker passes progress file' =>
        str_contains($patcher, '--progress-file", str(paths["progress_file"])'),
    'proxy failure prevents successful completion' =>
        str_contains($patcher, 'proxy_error or f"stems_python_exit_{return_code}"'),
    'worker current process becomes proxy process for cancellation' =>
        str_contains($patcher, 'self.current_process = proxy_proc'),
    'patcher backs up current worker before mutation' =>
        str_contains($patcher, 'shutil.copy2(WORKER, backup)'),
    'proxy builder is included' =>
        str_contains($proxy, 'OPUS_BITRATE = "192k"'),
    'playback queued flash is translated in stems domain' =>
        str_contains($controller, "translator->trans('stems.playback.queued', [], 'stems')"),
    'playback requirement flash is translated in stems domain' =>
        str_contains($controller, "translator->trans('stems.playback.requires_stems', [], 'stems')"),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R27.2 desktop-worker checks passed.\n");
