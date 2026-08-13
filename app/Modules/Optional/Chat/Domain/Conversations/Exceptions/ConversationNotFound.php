<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations\Exceptions;

use RuntimeException;

final class ConversationNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Conversation not found.');
    }
}
