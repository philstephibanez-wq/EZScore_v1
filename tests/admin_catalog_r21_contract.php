<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r21_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$message}\n");
}

$controller = file_get_contents($root.'/src/Controller/CatalogController.php');
r21_check(is_string($controller) && str_contains($controller, "action === 'imported'"), 'workspace can reset song to Imported');
r21_check(str_contains($controller, "admin_catalog_song_update"), 'admin catalog update route exists');
r21_check(str_contains($controller, "admin_catalog_song_delete"), 'admin catalog delete route exists');
r21_check(str_contains($controller, 'SongStatus::Analyzed && $song->getStatus() !== SongStatus::Analyzed'), 'Analyzed remains pipeline-owned');
r21_check(str_contains($controller, "'page', 50"), 'admin catalog is server paginated');
r21_check(str_contains($controller, "get('status'"), 'admin catalog has status filter');
r21_check(str_contains($controller, "get('editor'"), 'admin catalog has editor filter');

$base = file_get_contents($root.'/templates/base.html.twig');
$navStart = strpos($base, '<nav class="ez-drawer-nav">');
$navEnd = $navStart !== false ? strpos($base, '</nav>', $navStart) : false;
$navBlock = ($navStart !== false && $navEnd !== false) ? substr($base, $navStart, $navEnd - $navStart) : '';
r21_check($navBlock !== '' && !str_contains($navBlock, "path('app_profile'"), 'Profile removed from main drawer navigation');
r21_check(str_contains($base, 'ez-drawer-profile'), 'bottom user identity links to Profile');

$cover = file_get_contents($root.'/templates/layout/song/_cover.html.twig');
r21_check(is_string($cover) && str_contains($cover, 'data-cover-input'), 'cover file input is wired for preview');

$js = file_get_contents($root.'/public/assets/js/song-import.js');
r21_check(is_string($js) && str_contains($js, "URL.createObjectURL(file)"), 'cover/audio preview uses local object URL');
r21_check(str_contains($js, 'coverPreview.replaceChildren(image)'), 'cover preview replaces placeholder immediately');

$catalog = file_get_contents($root.'/templates/catalog/index.html.twig');
r21_check(is_string($catalog) && str_contains($catalog, 'admin-catalog-moderation'), 'admin moderation controls are rendered');
r21_check(str_contains($catalog, 'admin_catalog_song_delete'), 'admin delete control is rendered');

$cdc = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
r21_check(is_string($cdc) && str_contains($cdc, '## 15. Répertoire administrateur'), 'CDC includes R21 admin catalog requirements');

$recette = file_get_contents($root.'/recette.md');
r21_check(is_string($recette) && str_contains($recette, '## 20. Back-office Répertoire'), 'root recipe includes R21 acceptance tests');

fwrite(STDOUT, "\n{$checks} R21 checks passed.\n");
