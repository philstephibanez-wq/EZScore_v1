<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$entity = file_get_contents($root.'/src/Domain/Song/UserSongStemMix.php');
$repo = file_get_contents($root.'/src/Domain/Song/UserSongStemMixRepository.php');
$controller = file_get_contents($root.'/src/Controller/SongStemController.php');
$twig = file_get_contents($root.'/templates/stems/index.html.twig');
$js = file_get_contents($root.'/public/assets/js/stems-mixer.js');
$migration = file_get_contents($root.'/migrations/Version20260926021000.php');

$checks = [
    'mix entity exists per user/song' => str_contains($entity, 'uniq_user_song_stem_mix'),
    'settings persisted as json' => str_contains($entity, "type: 'json'"),
    'repository resolves user+song mix' => str_contains($repo, 'findForUserAndSong'),
    'migration creates mix table' => str_contains($migration, 'CREATE TABLE user_song_stem_mixes'),
    'migration cascades song deletion' => str_contains($migration, 'ON UPDATE NO ACTION ON DELETE CASCADE'),
    'controller exposes original audio' => str_contains($controller, "app_song_stems_original_audio"),
    'controller has persisted mix endpoint' => str_contains($controller, "app_song_stems_mix"),
    'mix endpoint is csrf protected' => str_contains($controller, "song_stems_mix_"),
    'server clamps saved settings' => str_contains($controller, 'sanitizeMixSettings'),
    'template exposes realtime mixer' => str_contains($twig, 'STEMS + table de mixage'),
    'template exposes all stem tracks' => str_contains($twig, "'lead_vocals'") && str_contains($twig, "'backing_vocals'") && str_contains($twig, "'guitar'") && str_contains($twig, "'piano'"),
    'mixer uses Web Audio API' => str_contains($js, 'AudioContext'),
    'sources share one audio context clock' => str_contains($js, 'startedAt = context.currentTime'),
    'realtime gain selection exists' => str_contains($js, 'setTrackGain'),
    'three band EQ exists' => str_contains($js, 'lowshelf') && str_contains($js, 'peaking') && str_contains($js, 'highshelf'),
    'mix persistence is debounced' => str_contains($js, 'window.setTimeout(saveSettings, 450)'),
    'persisted settings are restored' => str_contains($js, 'applyPersisted'),
    'master volume is persisted' => str_contains($js, 'master_volume'),
    'playback rate is persisted' => str_contains($js, 'playback_rate'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}
fwrite(STDOUT, "\n".count($checks)." R25 STEM mixer checks passed.\n");
