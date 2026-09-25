<?php

declare(strict_types=1);

namespace App\Domain\Playlist;

enum PlaylistInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
