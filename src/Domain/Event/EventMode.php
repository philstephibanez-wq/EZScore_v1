<?php
declare(strict_types=1);

namespace App\Domain\Event;

enum EventMode: string
{
    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';
}
