<?php

declare(strict_types=1);

namespace App\Shared\Application\Security\Contracts;

interface AdministrativeSecuritySettings
{
    public function adminModeInactivityTimeoutMinutes(): int;

    public function adminModeAbsoluteLifetimeMinutes(): int;

    public function adminHighRiskTimeoutMinutes(): int;

    public function mfaRequired(): bool;
}
