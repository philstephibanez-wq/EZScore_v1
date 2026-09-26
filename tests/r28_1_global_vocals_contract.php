<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$apply = file_get_contents($root.'/scripts/apply_r28_remove_global_vocals.py');
$cleanup = file_get_contents($root.'/scripts/cleanup_redundant_global_vocals.py');

$checks = [
    'patcher targets FINAL_STEMS structurally' =>
        str_contains($apply, 'remove_python_tuple_item') &&
        str_contains($apply, '"FINAL_STEMS"'),
    'patcher removes TRACKS vocals structurally' =>
        str_contains($apply, '"TRACKS"') &&
        str_contains($apply, '"vocals"'),
    'temporary vocals WAV is deleted after split' =>
        str_contains($apply, '(payload / "vocals.wav").unlink(missing_ok=True)'),
    'SongStemStorage constant is patched structurally' =>
        str_contains($apply, 'public const STEMS'),
    'SongStemPlaybackStorage constant is patched structurally' =>
        str_contains($apply, 'public const TRACKS'),
    'controller allow-list is patched structurally' =>
        str_contains($apply, '$allowedTracks'),
    'Twig vocals row is removed by key' =>
        str_contains($apply, 'Remove global vocals row from mixer'),
    'patcher is idempotent' =>
        str_contains($apply, 'already applied') &&
        str_contains($apply, 'already absent'),
    'patcher backs up modified files' =>
        str_contains($apply, 'BACKUP_DIR') &&
        str_contains($apply, 'shutil.copy2'),
    'cleanup is dry-run by default' =>
        str_contains($cleanup, 'Nothing deleted. Re-run with --apply'),
    'cleanup protects missing split pair' =>
        str_contains($cleanup, 'missing valid lead/backing pair'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}
fwrite(STDOUT, "\n".count($checks)." R28.1 checks passed.\n");
