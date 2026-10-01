<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Calls;

enum CallParticipantRole: string
{
    case Starter = 'starter';
    case Invitee = 'invitee';
    case Joiner = 'joiner';
}
