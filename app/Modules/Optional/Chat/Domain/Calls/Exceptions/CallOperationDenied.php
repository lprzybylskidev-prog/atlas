<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Calls\Exceptions;

use RuntimeException;

final class CallOperationDenied extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'The Call does not exist or is no longer available.');
    }

    public static function notParticipant(): self
    {
        return new self('not_participant', 'The user is not eligible to participate in this Call.');
    }

    public static function busy(): self
    {
        return new self('busy', 'The user is already participating in another RTC session.');
    }

    public static function invalidConversation(): self
    {
        return new self('invalid_conversation', 'Ad-hoc Calls require an active direct, group, or Team conversation.');
    }

    public static function invalidTransition(): self
    {
        return new self('invalid_transition', 'The requested Call state transition is not available.');
    }

    public static function idempotencyConflict(): self
    {
        return new self('idempotency_conflict', 'The Call request key was already used for another request.');
    }

    public static function screenShareBusy(): self
    {
        return new self('screen_share_busy', 'Another participant is already sharing a screen in this Call.');
    }
}
