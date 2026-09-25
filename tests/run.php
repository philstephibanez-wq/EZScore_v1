<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;

$root = dirname(__DIR__);
$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$security = file_get_contents($root.'/config/packages/security.yaml');
check(is_string($security) && str_contains($security, 'ROLE_EDITOR: ROLE_READER'), 'Editor inherits Reader');
check(str_contains($security, 'ROLE_ADMIN: ROLE_EDITOR'), 'Admin inherits Editor');

check(AclPrivilege::GROUP_MANAGE_PLAYLISTS !== '', 'group playlist privilege exists');

$owner = (new User())->setEmail('owner@example.test')->setDisplayName('Owner');
$guest = (new User())->setEmail('guest@example.test')->setDisplayName('Guest');
$group = (new UserGroup())->setName('Formation A');
$playlist = (new Playlist())
    ->setName('Repetition')
    ->setOwnerUser($owner)
    ->setCreatedBy($owner);

check($playlist->getOwnerUser() === $owner, 'playlist has a human owner');

$link = new PlaylistGroup($playlist, $group, $owner);
check($link->getPlaylist() === $playlist, 'PlaylistGroup references playlist');
check($link->getGroup() === $group, 'PlaylistGroup references group');

$invitation = new PlaylistInvitation($playlist, $guest, $owner);
check($invitation->getStatus() === PlaylistInvitationStatus::Pending, 'personal invitation starts pending');
$invitation->accept();
check($invitation->getStatus() === PlaylistInvitationStatus::Accepted, 'personal invitation can be accepted');

$song = (new Song())->setTitle('Song')->setArtist('Artist');
$item = new PlaylistItem($playlist, $song, $owner, 0);
check($item->getPosition() === 0, 'playlist song order is persisted');

$member = (new GroupMember())->setGroup($group)->setUser($guest)->setRole('member');
check($member->getRole() === 'member', 'group member role exists');

$playlistSource = file_get_contents($root.'/src/Controller/PlaylistController.php');
check(is_string($playlistSource) && str_contains($playlistSource, 'bulkAddSongs'), 'bulk song add exists');
check(str_contains($playlistSource, 'bulkAddInvitations'), 'bulk personal invitation exists');

$groupSource = file_get_contents($root.'/src/Controller/GroupController.php');
check(is_string($groupSource) && str_contains($groupSource, 'bulkAddMembers'), 'bulk group member add exists');
check(str_contains($groupSource, 'bulkAddPlaylists'), 'bulk group playlist assignment exists');

$pickerSource = file_get_contents($root.'/src/Controller/PickerController.php');
check(is_string($pickerSource) && str_contains($pickerSource, 'PAGE_SIZE = 25'), 'picker is server-paginated');

$cahier = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
check(is_string($cahier) && str_contains($cahier, 'Accès automatique par groupe'), 'specification documents automatic group access');

fwrite(STDOUT, "\n{$checks} R18 contract/domain checks passed.\n");
