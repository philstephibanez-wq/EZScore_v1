<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Domain\Event\Event;
use App\Domain\Event\EventMode;
use App\Domain\Event\EventParticipant;
use App\Domain\Event\EventParticipantStatus;
use App\Domain\Event\EventStatus;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;

$root = dirname(__DIR__);
$checks = 0;

function r19_check(bool $condition, string $message): void
{
    global $checks;
    ++$checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$creator = (new User())->setEmail('creator@example.test')->setDisplayName('Creator');
$guest = (new User())->setEmail('guest@example.test')->setDisplayName('Guest');

$event = (new Event($creator))
    ->setTitle('Répétition 30/10/2026')
    ->setMode(EventMode::Hybrid)
    ->setStatus(EventStatus::Scheduled);

r19_check($event->getCreatedBy() === $creator, 'event keeps its creator');
r19_check($event->getMode() === EventMode::Hybrid, 'event mode persisted in domain');
r19_check(AclPrivilege::EVENT_VIEW === 'EVENT_VIEW', 'EVENT_VIEW privilege exists');
r19_check(AclPrivilege::EVENT_MANAGE === 'EVENT_MANAGE', 'EVENT_MANAGE privilege exists');
r19_check(AclPrivilege::EVENT_INVITE === 'EVENT_INVITE', 'EVENT_INVITE privilege exists');

$participant = new EventParticipant($event, $guest);
r19_check($participant->getStatus() === EventParticipantStatus::Invited, 'participant starts invited');
$participant->respond(EventParticipantStatus::Accepted);
r19_check($participant->getStatus() === EventParticipantStatus::Accepted, 'participant can RSVP accepted');

$controller = file_get_contents($root.'/src/Controller/EventController.php');
r19_check(is_string($controller) && str_contains($controller, 'bulk-add'), 'bulk participant endpoint exists');
r19_check(str_contains($controller, 'PlaylistGroup'), 'group event can attach its playlist to the group');

$picker = file_get_contents($root.'/src/Controller/EventPickerController.php');
r19_check(is_string($picker) && str_contains($picker, 'PAGE_SIZE = 25'), 'event pickers are server paginated');

$template = file_get_contents($root.'/templates/events/show.html.twig');
r19_check(is_string($template) && str_contains($template, 'wa.me'), 'WhatsApp share exists');
r19_check(str_contains($template, 'facebook.com/sharer'), 'Facebook share exists');
r19_check(str_contains($template, 'twitter.com/intent/tweet'), 'X share exists');

$cahier = file_get_contents($root.'/docs/CAHIER_DES_CHARGES.md');
r19_check(is_string($cahier) && str_contains($cahier, '## 11. Événements'), 'requirements include events');
r19_check(str_contains($cahier, 'Le SMS n\'est pas retenu.'), 'requirements explicitly exclude SMS');

$recette = file_get_contents($root.'/recette.md');
r19_check(is_string($recette) && str_contains($recette, 'Événements — création'), 'root recipe contains event acceptance');

fwrite(STDOUT, "\n{$checks} R19 checks passed.\n");
