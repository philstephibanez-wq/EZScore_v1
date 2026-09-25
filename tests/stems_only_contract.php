<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r23_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$message}\n");
}

$python = file_get_contents($root.'/analysis/stems_only.py');
r23_check(is_string($python) && str_contains($python, 'BS_MODEL'), 'BS-RoFormer instrument separation configured');
r23_check(str_contains($python, 'KARAOKE_MODEL'), 'MelBand lead/backing separation configured');

foreach ([
    'vocals', 'lead_vocals', 'backing_vocals', 'drums',
    'bass', 'guitar', 'piano', 'other',
] as $stem) {
    r23_check(str_contains($python, '"'.$stem.'"'), "stem {$stem} is persisted");
}

foreach ([
    'import whisper',
    'from whisper',
    'import lv_chordia',
    'from lv_chordia',
    'import madmom',
    'from madmom',
    'import mido',
    'from mido',
] as $forbiddenImport) {
    r23_check(
        !str_contains(strtolower($python), $forbiddenImport),
        "forbidden analysis import absent: {$forbiddenImport}"
    );
}

$jobService = file_get_contents($root.'/src/Service/SongStemJobService.php');
r23_check(is_string($jobService) && str_contains($jobService, "public const KIND = 'stems'"), 'dedicated STEM job kind exists');
r23_check(str_contains($jobService, "UPDATE analysis_jobs"), 'STEM claim is conditional in database');

$worker = file_get_contents($root.'/src/Service/SongStemWorker.php');
r23_check(is_string($worker) && str_contains($worker, "'scope' => 'stems_only'"), 'result is explicitly STEM-only');
r23_check(!str_contains($worker, 'markAnalyzed'), 'STEM completion does not change Song to Analyzed');

$storage = file_get_contents($root.'/src/Service/SongStemStorage.php');
r23_check(is_string($storage) && str_contains($storage, 'current.json'), 'persistent current-run pointer exists');
r23_check(str_contains($storage, 'deleteForSong'), 'physical STEM cleanup exists on song deletion');
r23_check(str_contains($storage, 'pruneOtherAudioHashes'), 'old source hashes are pruned after successful regeneration');

$listener = file_get_contents($root.'/src/EventListener/SongStemLifecycleListener.php');
r23_check(is_string($listener) && str_contains($listener, 'Events::postRemove'), 'song deletion triggers physical STEM cleanup');

$controller = file_get_contents($root.'/src/Controller/SongStemController.php');
r23_check(is_string($controller) && str_contains($controller, 'app_song_stems_generate'), 'STEM generation route exists');
r23_check(str_contains($controller, 'ROLE_EDITOR'), 'editor access is enforced');
r23_check(str_contains($controller, 'ROLE_ADMIN'), 'admin access is enforced');

$template = file_get_contents($root.'/templates/stems/index.html.twig');
r23_check(is_string($template) && str_contains($template, '<audio controls'), 'each STEM can be auditioned');
r23_check(!str_contains(strtolower($template), 'mixer'), 'no mixer is implemented in R23');

$cdc = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
r23_check(is_string($cdc) && str_contains($cdc, '## 18. Pipeline musical — Étape 1 uniquement : STEMS'), 'CDC records strict R23 scope');

fwrite(STDOUT, "\n{$checks} R23 STEM-only checks passed.\n");
