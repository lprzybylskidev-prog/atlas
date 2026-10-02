<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Meetings;

enum MeetingMutationScope: string
{
    case Occurrence = 'occurrence';
    case Future = 'future';
    case Series = 'series';
}
