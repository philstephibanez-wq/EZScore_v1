<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$proxy = file_get_contents($root.'/analysis/build_playback_proxies.py');
$playback = file_get_contents($root.'/src/Service/SongStemPlaybackStorage.php');
$worker = file_get_contents($root.'/src/Service/SongStemWorker.php');
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');
$twig = file_get_contents($root.'/templates/stems/index.html.twig');

$checks = [
    'Opus proxy builder exists' => str_contains($proxy, 'libopus'),
    'proxy quality is 192k VBR' => str_contains($proxy, 'OPUS_BITRATE = "192k"') && str_contains($proxy, '"-vbr", "on"'),
    'analytical WAV files are not replaced' => str_contains($proxy, 'Missing analytical STEM WAV'),
    'original gets a playback proxy' => str_contains($proxy, '"original"'),
    'all 8 stems get playback proxies' => str_contains($proxy, '"lead_vocals"') && str_contains($proxy, '"other"'),
    'proxy storage is per immutable run' => str_contains($proxy, 'playback_root / run_name'),
    'playback storage resolves current run sidecar' => str_contains($playback, "DIRECTORY_SEPARATOR . 'playback'"),
    'worker automatically builds proxies after STEMS' => str_contains($worker, 'build_playback_proxies.py'),
    'cached STEM jobs can backfill proxies' => str_contains($worker, 'R27: build lightweight playback proxies'),
    'worker validates proxy completeness' => str_contains($worker, '$this->playback->isReady($song)'),
    'controller exposes Opus streaming route' => str_contains($controller, "app_song_stems_playback_audio"),
    'controller advertises byte ranges for proxies' => str_contains($controller, "Accept-Ranges', 'bytes"),
    'controller can queue proxy preparation only' => str_contains($controller, "app_song_stems_playback_build") && str_contains($controller, '$jobs->queue($song, $user, false)'),
    'template uses Opus original proxy' => str_contains($twig, "name:'original'"),
    'template does not instantiate mixer before proxies exist' => str_contains($twig, 'stems_complete and playback_ready'),
    'template offers proxy preparation for old songs' => str_contains($twig, 'app_song_stems_playback_build'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R27 Opus playback checks passed.\n");
