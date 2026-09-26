<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root.'/scripts/fix_translation_encoding_r25_2.php';

$cases = [
    'R├®pertoire' => 'Répertoire',
    'Fran├ºais' => 'Français',
    'biblioth├¿que' => 'bibliothèque',
    'dΓÇÖacc├¿s' => 'd’accès',
    'Cr├®er' => 'Créer',
    'D├®connexion' => 'Déconnexion',
];

foreach ($cases as $broken => $expected) {
    $actual = ezscoreRepairMojibake($broken);

    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$broken} => {$actual}; expected {$expected}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$broken} => {$actual}\n");
}

$healthy = "Répertoire — bibliothèque — Français — d’accès — Créer";
if (ezscoreRepairMojibake($healthy) !== $healthy) {
    fwrite(STDERR, "FAIL: healthy UTF-8 text was modified.\n");
    exit(1);
}
fwrite(STDOUT, "OK: healthy UTF-8 remains unchanged.\n");

$ps = file_get_contents($root.'/scripts/restore_full_translations_r25_1.ps1');
if (!is_string($ps) || str_contains($ps, 'git show')) {
    fwrite(STDERR, "FAIL: old git-show restore path is still present.\n");
    exit(1);
}
fwrite(STDOUT, "OK: unsafe PowerShell git-show restore removed.\n");

fwrite(STDOUT, "\n8 R25.2 translation-encoding checks passed.\n");
