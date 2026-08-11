<?php

declare(strict_types=1);

namespace App\Shared\Application\Security\Contracts;

interface PasswordSecuritySettings
{
    public function passwordExpiresAfterDays(): int;
}
