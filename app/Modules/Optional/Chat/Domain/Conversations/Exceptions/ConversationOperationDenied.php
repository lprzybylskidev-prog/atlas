<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations\Exceptions;

use RuntimeException;

final class ConversationOperationDenied extends RuntimeException
{
    public static function inactiveUser(): self
    {
        return new self('An inactive Atlas user cannot join new communication.');
    }

    public static function notOwner(): self
    {
        return new self('Only the group owner may perform this operation.');
    }

    public static function notMember(): self
    {
        return new self('The user is not an active conversation member.');
    }

    public static function ownerMustTransfer(): self
    {
        return new self('The group owner must transfer ownership before leaving.');
    }

    public static function invalidConversationType(): self
    {
        return new self('The operation is not valid for this conversation type.');
    }
}
