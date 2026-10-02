<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingRecordingSegmentStatus: string
{
    case Starting = 'starting';
    case Recording = 'recording';
    case Stopping = 'stopping';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
