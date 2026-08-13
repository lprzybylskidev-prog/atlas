<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Messages;

enum MentionType: string
{
    case User = 'user';
    case Everyone = 'everyone';
    case Online = 'online';
}
