<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingRecordingStatus: string
{
    case Starting = 'starting';
    case Recording = 'recording';
    case Pausing = 'pausing';
    case Paused = 'paused';
    case Resuming = 'resuming';
    case Stopping = 'stopping';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Removed = 'removed';

    public function isActive(): bool
    {
        return in_array($this, [self::Starting, self::Recording, self::Pausing, self::Paused, self::Resuming, self::Stopping], true);
    }
}
