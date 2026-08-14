<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Exceptions;

use RuntimeException;

final class ChatAccessDenied extends RuntimeException
{
    public static function forReason(string $reason): self
    {
        return new self(sprintf('Chat module access denied: %s.', $reason));
    }
}
