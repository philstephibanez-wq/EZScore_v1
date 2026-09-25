<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Group\GroupMember;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistItem;
use App\Domain\Song\Song;
use App\Domain\User\User;

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

$envDev = file_get_contents($root . '/.env.dev');
check(is_string($envDev), '.env.dev is readable');
check(!preg_match('/^APP_SECRET=[a-f0-9]{24,}$/mi', $envDev), '.env.dev contains no committed real-looking APP_SECRET');

$gitignore = file_get_contents($root . '/.gitignore');
check(is_string($gitignore) && str_contains($gitignore, '/public/uploads/'), 'runtime public uploads are ignored by Git');

$deadPaths = [
    'assets/css/app.css',
    'assets/js/app.js',
    'public/assets/js/interaction-feedback.js',
    'translations/messages.r741.fr.yaml',
    'translations/messages.r741.en.yaml',
    'src/Controller/.gitignore',
    'src/Entity/.gitignore',
    'src/Repository/.gitignore',
    'translations/.gitignore',
    'migrations/.gitignore',
];
foreach ($deadPaths as $deadPath) {
    check(!file_exists($root . '/' . $deadPath), 'obsolete path removed: ' . $deadPath);
}

$groupController = file_get_contents($root . '/src/Controller/GroupController.php');
check(is_string($groupController) && !str_contains($groupController, "denyAccessUnlessGranted('ROLE_ADMIN')"), 'group creation is not restricted to admins');
check(str_contains($groupController, "->setRole('owner')"), 'group creator is persisted as owner');
check(str_contains($groupController, 'countGroupOwners'), 'last-owner invariant is enforced');

$song = (new Song())->setTitle('Contract Song')->setArtist('EZScore');
foreach (['auto', '2/4', '3/4', '4/4', '5/4', '6/8', '9/8', '12/8'] as $signature) {
    $song->setTimeSignature($signature);
    check($song->getTimeSignature() === $signature, 'time signature accepted: ' . $signature);
}
expectInvalidArgument(static fn() => $song->setTimeSignature('7/8'), 'unsupported time signature is rejected');
$song->setCapo(0)->setCapo(11);
expectInvalidArgument(static fn() => $song->setCapo(12), 'capo > 11 is rejected');

$user = (new User())->setEmail('contract@example.test')->setDisplayName('Contract User');
$playlist = (new Playlist())->setName('Contract Playlist')->setOwnerType('user')->setOwnerId(1)->setCreatedBy($user);
$item = new PlaylistItem($playlist, $song, $user, 0);
check($item->getPlaylist() === $playlist, 'PlaylistItem references Playlist');
check($item->getSong() === $song, 'PlaylistItem references Song');
check($item->getPosition() === 0, 'PlaylistItem initial position is stored');
$item->setPosition(2);
check($item->getPosition() === 2, 'PlaylistItem position can be updated');
expectInvalidArgument(static fn() => $item->setPosition(-1), 'negative playlist position is rejected');

$member = (new GroupMember())->setUser($user)->setRole('owner');
check($member->getRole() === 'owner', 'group owner role is supported');
$member->setRole('manager');
check($member->getRole() === 'manager', 'group manager role is supported');
$member->setRole('member');
check($member->getRole() === 'member', 'group member role is supported');

fwrite(STDOUT, "\n{$checks} contract/domain checks passed.\n");
