<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$engine = file_get_contents($root.'/public/assets/js/audio/ezscore-audio-engine.js');
$mixer = file_get_contents($root.'/public/assets/js/stems-mixer.js');
$twig = file_get_contents($root.'/templates/stems/index.html.twig');
$css = file_get_contents($root.'/public/assets/css/stems.css');
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');

$checks = [
    'dedicated AudioEngine exists' => str_contains($engine, 'class EZScoreAudioEngine'),
    'uses streaming media elements' => str_contains($engine, "document.createElement('audio')"),
    'uses MediaElementAudioSourceNode path' => str_contains($engine, 'createMediaElementSource'),
    'does not decode complete files into AudioBuffer' => !str_contains($engine, 'decodeAudioData'),
    'tracks default to preload none' => str_contains($engine, "media.preload = 'none'"),
    'navigation abort removes media src' => str_contains($engine, "media.removeAttribute('src')") && str_contains($engine, 'media.load()'),
    'navigation capture disposes synchronously' => str_contains($engine, "document.addEventListener('click', this._navigationHandler, true)"),
    'pagehide disposes engine' => str_contains($engine, "window.addEventListener('pagehide'"),
    'single master timeline exposed' => str_contains($engine, 'currentTime()') && str_contains($engine, "CustomEvent('timeupdate'"),
    'drift correction exists' => str_contains($engine, 'syncThresholdSeconds') && str_contains($engine, '_syncNow'),
    'Web Audio gain remains' => str_contains($engine, 'createGain'),
    'three-band EQ remains' => str_contains($engine, "low.type = 'lowshelf'") && str_contains($engine, "mid.type = 'peaking'") && str_contains($engine, "high.type = 'highshelf'"),
    'mixer keeps persistence' => str_contains($mixer, 'saveSettings') && str_contains($mixer, 'persisted.tracks'),
    'per-track loading spinner is wired' => str_contains($twig, 'data-track-loader') && str_contains($mixer, 'track.loader.hidden'),
    'spinner CSS exists' => str_contains($css, '@keyframes stem-track-spin'),
    'original route uses same source resolver as worker' => str_contains($controller, '$storage->sourcePath($song)'),
    'original route advertises byte ranges' => str_contains($controller, "Accept-Ranges', 'bytes"),
    'R26 scripts are cache-busted' => str_contains($twig, 'ezscore-audio-engine.js?v=20260926r26') && str_contains($twig, 'stems-mixer.js?v=20260926r26'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R26 AudioEngine checks passed.\n");
