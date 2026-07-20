<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Enums;

enum ProcessRunStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Running = 'running';
    case Waiting = 'waiting';
    case Succeeded = 'succeeded';
    case SucceededWithWarnings = 'succeeded_with_warnings';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function terminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::SucceededWithWarnings,
            self::Failed,
            self::Cancelled,
            self::Expired,
        ], true);
    }

    public function active(): bool
    {
        return in_array($this, [self::Draft, self::Queued, self::Running, self::Waiting], true);
    }
}
