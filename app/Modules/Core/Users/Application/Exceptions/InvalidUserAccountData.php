<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exceptions;

use InvalidArgumentException;

final class InvalidUserAccountData extends InvalidArgumentException
{
    public static function missingName(): self
    {
        return new self('User account name is required.');
    }

    public static function invalidEmail(): self
    {
        return new self('User account email must be a valid email address.');
    }

    public static function duplicateEmail(string $email): self
    {
        return new self(sprintf('User account email [%s] already exists.', $email));
    }

    public static function missingTeamForOnboardingPackage(): self
    {
        return new self('A team public ID is required when applying a preset.');
    }

    public static function conflictingAuthorizationSources(): self
    {
        return new self('Choose either a preset or a source user to copy authorization from.');
    }

    public static function missingTeamForAuthorizationAssignment(): self
    {
        return new self('A team public ID is required when assigning user authorization.');
    }
}
