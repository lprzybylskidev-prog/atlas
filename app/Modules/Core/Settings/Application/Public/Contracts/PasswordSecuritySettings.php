<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Public\Contracts;

interface PasswordSecuritySettings
{
    public function passwordExpiresAfterDays(): int;
}
