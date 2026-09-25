<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function r20_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$message}\n");
}

$pagination = file_get_contents($root.'/src/Service/ListPagination.php');
r20_check(is_string($pagination) && str_contains($pagination, 'COUNT(DISTINCT'), 'shared pagination counts distinct root rows');
r20_check(str_contains($pagination, 'setMaxResults($perPage)'), 'shared pagination limits hydration');

$adminUsers = file_get_contents($root.'/src/Controller/AdminUserController.php');
r20_check(is_string($adminUsers) && str_contains($adminUsers, "get('role'"), 'admin users has role filter');
r20_check(str_contains($adminUsers, "get('google'"), 'admin users has provider filter');
r20_check(str_contains($adminUsers, "'page', 50"), 'admin users are server-paginated');

$groups = file_get_contents($root.'/src/Controller/GroupController.php');
r20_check(is_string($groups) && str_contains($groups, "get('member'"), 'groups can filter by member');
r20_check(str_contains($groups, "get('playlist'"), 'groups can filter by linked playlist');
r20_check(str_contains($groups, 'setMaxResults(8)'), 'nested group collections are bounded');

$playlists = file_get_contents($root.'/src/Controller/PlaylistController.php');
r20_check(is_string($playlists) && str_contains($playlists, "get('scope'"), 'playlists have access scope filter');
r20_check(str_contains($playlists, "get('song'"), 'playlists can filter by contained song');
r20_check(str_contains($playlists, "'page', 12"), 'playlist cards are server-paginated');
r20_check(str_contains($playlists, 'setMaxResults(10)'), 'playlist song preview is bounded');

$events = file_get_contents($root.'/src/Controller/EventController.php');
r20_check(is_string($events) && str_contains($events, "get('period'"), 'sessions have period filter');
r20_check(str_contains($events, "'ppage',"), 'session participant list has independent pagination');
r20_check(str_contains($events, "get('pstatus'"), 'session participants have RSVP filter');

$adminTemplate = file_get_contents($root.'/templates/admin/users.html.twig');
r20_check(is_string($adminTemplate) && str_contains($adminTemplate, 'list-alphabet'), 'admin users expose alphabet index');

$groupTemplate = file_get_contents($root.'/templates/groups/index.html.twig');
r20_check(is_string($groupTemplate) && str_contains($groupTemplate, 'preview_only'), 'group page tells user when collection is previewed');

$playlistTemplate = file_get_contents($root.'/templates/playlists/index.html.twig');
r20_check(is_string($playlistTemplate) && str_contains($playlistTemplate, 'share_counts_by_playlist'), 'playlist shares use scalable counters');

$sessionTemplate = file_get_contents($root.'/templates/events/show.html.twig');
r20_check(is_string($sessionTemplate) && str_contains($sessionTemplate, 'participant_pager'), 'session participants use paginated UI');

$cdc = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
r20_check(is_string($cdc) && str_contains($cdc, '## 14. Scalabilité globale'), 'CDC contains global scalability contract');

$recette = file_get_contents($root.'/recette.md');
r20_check(is_string($recette) && str_contains($recette, '## 19. Scalabilité / filtres globaux R20'), 'root recipe contains R20 acceptance tests');

fwrite(STDOUT, "\n{$checks} R20 scalability checks passed.\n");
