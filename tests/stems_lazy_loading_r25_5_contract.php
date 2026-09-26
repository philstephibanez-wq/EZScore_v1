<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root.'/public/assets/js/stems-mixer.js');

$checks = [
    'no eager loadAll remains' => !str_contains($js, 'const loadAll ='),
    'no Promise.allSettled preload remains' => !str_contains($js, 'Promise.allSettled'),
    'tracks are loaded lazily' => str_contains($js, 'const ensureTrackLoaded = async'),
    'only enabled tracks load before playback' => str_contains($js, 'ensureEnabledTracksLoaded'),
    'disabled track does not fetch at page load' => str_contains($js, 'if (!track.enabled.checked)'),
    'newly enabled track can load during playback' => str_contains($js, "track.enabled.addEventListener('change', async"),
    'single AudioContext clock is preserved' => str_contains($js, 'startedAt = context.currentTime'),
    'real-time gain is preserved' => str_contains($js, 'setTrackGain'),
    'EQ is preserved' => str_contains($js, "low.type = 'lowshelf'") && str_contains($js, "mid.type = 'peaking'") && str_contains($js, "high.type = 'highshelf'"),
    'mix persistence remains' => str_contains($js, 'saveSettings') && str_contains($js, 'applyPersisted'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R25.5 lazy-loading checks passed.\n");
