<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

enum ConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';
    case Team = 'team';
    case Meeting = 'meeting';
}
