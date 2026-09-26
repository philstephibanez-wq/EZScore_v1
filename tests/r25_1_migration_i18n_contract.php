<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$migration = file_get_contents($root.'/migrations/Version20260926021000.php');
$twig = file_get_contents($root.'/templates/stems/index.html.twig');
$fr = file_get_contents($root.'/translations/stems.fr.yaml');
$en = file_get_contents($root.'/translations/stems.en.yaml');
$js = file_get_contents($root.'/public/assets/js/stems-mixer.js');
$restore = file_get_contents($root.'/scripts/restore_full_translations_r25_1.ps1');

$checks = [
    'SQLite migration no inline -- json comment' => !str_contains($migration, '--(DC2Type:json)'),
    'SQLite settings column keeps comma' => str_contains($migration, 'settings CLOB NOT NULL,'),
    'French mixer translations exist' => str_contains($fr, 'mixer:') && str_contains($fr, 'title: STEMS + table de mixage'),
    'English mixer translations exist' => str_contains($en, 'mixer:') && str_contains($en, 'title: STEMS + mixer'),
    'Twig uses mixer translation keys' => str_contains($twig, "stems.mixer.title") && str_contains($twig, "stems.mixer.description"),
    'Twig exports JS translations' => str_contains($twig, 'data-i18n-saved=') && str_contains($twig, 'data-i18n-playing='),
    'JS consumes translated strings' => str_contains($js, 'root.dataset.i18nSaved') && str_contains($js, 't.playing'),
    'global translation restore uses healthy parent commit' => str_contains($restore, 'f2aaf304a438447a9231be3c5415946f1c2eb77d'),
    'global restore retains worker offline translations' => str_contains($restore, 'analysis_worker:'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$label}\n");
}
fwrite(STDOUT, "\n".count($checks)." R25.1 migration/i18n checks passed.\n");
