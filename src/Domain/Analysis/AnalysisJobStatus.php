<?php

declare(strict_types=1);

namespace App\Domain\Analysis;

enum AnalysisJobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
