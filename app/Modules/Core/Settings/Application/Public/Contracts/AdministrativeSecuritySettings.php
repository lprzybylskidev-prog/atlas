<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Public\Contracts;

interface AdministrativeSecuritySettings
{
    public function adminModeInactivityTimeoutMinutes(): int;

    public function adminModeAbsoluteLifetimeMinutes(): int;

    public function adminHighRiskTimeoutMinutes(): int;

    public function mfaRequired(): bool;
}
