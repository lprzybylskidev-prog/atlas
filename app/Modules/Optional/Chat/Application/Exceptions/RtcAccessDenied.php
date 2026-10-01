<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Exceptions;

use RuntimeException;

final class RtcAccessDenied extends RuntimeException
{
    public static function sessionUnavailable(): self
    {
        return new self('RTC session access is not authorized or the session is unavailable.');
    }

    public static function inPersonMeeting(): self
    {
        return new self('In-person Meetings do not create RTC rooms or participant access tokens.');
    }

    public static function participantMismatch(): self
    {
        return new self('RTC authorization did not resolve the requested participant.');
    }

    public static function sessionMismatch(): self
    {
        return new self('RTC authorization did not resolve the requested session.');
    }
}
