<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Exceptions;

use RuntimeException;

final class MeetingOperationDenied extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('Meeting not found.');
    }

    public static function notInvited(): self
    {
        return new self('Meeting access requires an active invitation.');
    }

    public static function organizerOnly(): self
    {
        return new self('Only the Meeting organizer may perform this operation.');
    }

    public static function organizerCannotBeRemoved(): self
    {
        return new self('The Meeting organizer cannot be removed.');
    }

    public static function alreadyInvited(): self
    {
        return new self('The user is already invited to this Meeting.');
    }

    public static function rtcUnavailable(): self
    {
        return new self('The Meeting RTC session is unavailable.');
    }

    public static function invalidRtcAction(): self
    {
        return new self('The requested Meeting RTC action is invalid.');
    }

    public static function locked(): self
    {
        return new self('The Meeting is locked.');
    }
}
