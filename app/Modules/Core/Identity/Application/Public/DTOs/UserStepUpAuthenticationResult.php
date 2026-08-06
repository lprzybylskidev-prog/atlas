<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class UserStepUpAuthenticationResult
{
    private function __construct(
        public bool $verified,
        public bool $passwordValid,
        public bool $mfaValid,
    ) {}

    public static function verified(): self
    {
        return new self(true, true, true);
    }

    public static function passwordRejected(): self
    {
        return new self(false, false, true);
    }

    public static function mfaRejected(): self
    {
        return new self(false, true, false);
    }
}
