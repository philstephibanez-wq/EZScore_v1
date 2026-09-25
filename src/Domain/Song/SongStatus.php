<?php

declare(strict_types=1);

namespace App\Domain\Song;

enum SongStatus: string
{
    case Imported = 'imported';
    case Analyzed = 'analyzed';
    case Editing = 'editing';
    case Published = 'published';
}
