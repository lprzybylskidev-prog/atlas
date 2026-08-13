<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

enum ConversationMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
