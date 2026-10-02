<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum TranscriptionStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function active(): bool
    {
        return in_array($this, [self::Queued, self::Submitted, self::Processing], true);
    }
}
