<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Domain\Event\Event;
use App\Domain\Event\EventType;
use App\Domain\User\User;

$user = (new User())->setEmail('session@example.test')->setDisplayName('Session Owner');
$event = new Event($user);

if ($event->getType() !== EventType::Session) {
    fwrite(STDERR, "FAIL: default Event type must be Session\n");
    exit(1);
}

$fr = file_get_contents(dirname(__DIR__).'/translations/event.fr.yaml');
if (!is_string($fr) || !str_contains($fr, 'nav: Sessions')) {
    fwrite(STDERR, "FAIL: FR UI must expose Sessions\n");
    exit(1);
}

fwrite(STDOUT, "OK: R19.1 session type contract\n");
