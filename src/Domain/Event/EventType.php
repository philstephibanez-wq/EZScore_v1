<?php

declare(strict_types=1);

namespace App\Domain\Event;

enum EventType: string
{
    case Session = 'session';
}
