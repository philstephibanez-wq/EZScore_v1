<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Group\GroupMember;
use App\Domain\Playlist\Playlist;
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

function expectInvalidArgument(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\InvalidArgumentException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

function expectDomainException(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\DomainException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}

$envDev = file_get_contents($root . '/.env.dev');
check(is_string($envDev), '.env.dev is readable');
check(!preg_match('/^APP_SECRET=[a-f0-9]{24,}$/mi', $envDev), '.env.dev contains no committed real-looking APP_SECRET');

$security = file_get_contents($root . '/config/packages/security.yaml');
check(is_string($security) && str_contains($security, 'role_hierarchy:'), 'Symfony role hierarchy is configured');
check(str_contains($security, 'ROLE_EDITOR: ROLE_READER'), 'ROLE_EDITOR inherits ROLE_READER');
check(str_contains($security, 'ROLE_ADMIN: ROLE_EDITOR'), 'ROLE_ADMIN inherits ROLE_EDITOR');

foreach ([
    AclPrivilege::PLAYLIST_VIEW,
    AclPrivilege::PLAYLIST_EDIT,
    AclPrivilege::PLAYLIST_INVITE,
    AclPrivilege::GROUP_CREATE,
    AclPrivilege::GROUP_MANAGE_MEMBERS,
    AclPrivilege::SONG_VIEW,
    AclPrivilege::SONG_EDIT,
] as $privilege) {
    check($privilege !== '', 'ACL privilege is defined: '.$privilege);
}

$song = (new Song())->setTitle('Contract Song')->setArtist('EZScore');
foreach (['auto', '2/4', '3/4', '4/4', '5/4', '6/8', '9/8', '12/8'] as $signature) {
    $song->setTimeSignature($signature);
    check($song->getTimeSignature() === $signature, 'time signature accepted: '.$signature);
}
expectInvalidArgument(static fn() => $song->setTimeSignature('7/8'), 'unsupported time signature is rejected');
$song->setCapo(0)->setCapo(11);
expectInvalidArgument(static fn() => $song->setCapo(12), 'capo > 11 is rejected');

$owner = (new User())->setEmail('owner@example.test')->setDisplayName('Owner');
$guest = (new User())->setEmail('guest@example.test')->setDisplayName('Guest');
$playlist = (new Playlist())->setName('Contract Playlist')->setOwnerType('user')->setOwnerId(1)->setCreatedBy($owner);

$item = new PlaylistItem($playlist, $song, $owner, 0);
check($item->getPosition() === 0, 'PlaylistItem initial position is stored');
$item->setPosition(2);
check($item->getPosition() === 2, 'PlaylistItem position can be updated');
expectInvalidArgument(static fn() => $item->setPosition(-1), 'negative playlist position is rejected');

$invitation = new PlaylistInvitation($playlist, $guest, $owner);
check($invitation->getStatus() === PlaylistInvitationStatus::Pending, 'playlist invitation starts pending');
$invitation->accept();
check($invitation->getStatus() === PlaylistInvitationStatus::Accepted, 'playlist invitation can be accepted');
expectDomainException(static fn() => $invitation->accept(), 'accepted invitation cannot be accepted twice');
$invitation->cancel();
check($invitation->getStatus() === PlaylistInvitationStatus::Cancelled, 'accepted sharing can be revoked');
$invitation->reopen($owner);
check($invitation->getStatus() === PlaylistInvitationStatus::Pending, 'cancelled invitation can be reopened');
$invitation->decline();
check($invitation->getStatus() === PlaylistInvitationStatus::Declined, 'pending invitation can be declined');

$member = (new GroupMember())->setUser($owner)->setRole('owner');
check($member->getRole() === 'owner', 'group owner role is supported');
$member->setRole('manager');
check($member->getRole() === 'manager', 'group manager role is supported');
$member->setRole('member');
check($member->getRole() === 'member', 'group member role is supported');

$playlistController = file_get_contents($root . '/src/Controller/PlaylistController.php');
check(is_string($playlistController) && str_contains($playlistController, 'denyAccessUnlessGranted(AclPrivilege::PLAYLIST_EDIT'), 'playlist mutations use Symfony voters');
check(!str_contains($playlistController, 'canManagePlaylist('), 'legacy playlist permission helper is removed');

$groupController = file_get_contents($root . '/src/Controller/GroupController.php');
check(is_string($groupController) && str_contains($groupController, 'denyAccessUnlessGranted(AclPrivilege::GROUP_CREATE'), 'group creation uses Symfony voter');
check(!str_contains($groupController, 'canEditGroup('), 'legacy group permission helper is removed');

fwrite(STDOUT, "\n{$checks} contract/domain/ACL checks passed.\n");
