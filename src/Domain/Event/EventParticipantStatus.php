<?php
declare(strict_types=1);

namespace App\Domain\Event;

enum EventParticipantStatus: string
{
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Maybe = 'maybe';
}
