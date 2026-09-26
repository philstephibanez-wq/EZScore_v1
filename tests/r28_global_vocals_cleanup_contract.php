<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$apply = file_get_contents($root.'/scripts/apply_r28_remove_global_vocals.py');
$cleanup = file_get_contents($root.'/scripts/cleanup_redundant_global_vocals.py');

$checks = [
    'future analytical set drops global vocals' =>
        str_contains($apply, 'Remove vocals from persistent STEM set'),
    'future runs stop persisting vocals.wav' =>
        str_contains($apply, 'Stop persisting vocals.wav'),
    'future playback drops vocals.opus' =>
        str_contains($apply, 'Remove vocals.opus from proxy track set'),
    'SongStemStorage no longer requires vocals' =>
        str_contains($apply, 'Remove vocals from SongStemStorage::STEMS'),
    'playback storage no longer requires vocals' =>
        str_contains($apply, 'Remove vocals from playback TRACKS'),
    'mixer row is removed' =>
        str_contains($apply, 'Remove global vocals row from mixer'),
    'mixer persistence allow-list is cleaned' =>
        str_contains($apply, 'Remove vocals from persisted mixer allow-list'),
    'cleanup protects runs without lead/backing' =>
        str_contains($cleanup, 'missing valid lead/backing pair'),
    'cleanup handles WAV global vocals' =>
        str_contains($cleanup, 'vocals.wav'),
    'cleanup handles Opus global vocals' =>
        str_contains($cleanup, 'vocals.opus'),
    'cleanup updates analytical manifests' =>
        str_contains($cleanup, '"stems", "vocals"'),
    'cleanup updates playback manifests' =>
        str_contains($cleanup, '"tracks", "vocals"'),
    'cleanup defaults to dry-run' =>
        str_contains($cleanup, 'Nothing deleted. Re-run with --apply'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}

fwrite(STDOUT, "\n".count($checks)." R28 global-vocals cleanup checks passed.\n");
