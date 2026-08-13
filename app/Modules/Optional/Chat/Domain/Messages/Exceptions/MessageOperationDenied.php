<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Messages\Exceptions;

use RuntimeException;

final class MessageOperationDenied extends RuntimeException
{
    public static function notParticipant(): self
    {
        return new self('The user is not authorized to access this conversation message.');
    }

    public static function notAuthor(): self
    {
        return new self('Only the message author may edit it.');
    }

    public static function unavailableMessage(): self
    {
        return new self('The message is not available to this user.');
    }

    public static function invalidReference(): self
    {
        return new self('The referenced message does not belong to the required conversation.');
    }

    public static function invalidMention(): self
    {
        return new self('Only active conversation participants may be mentioned.');
    }
}
