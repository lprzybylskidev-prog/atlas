<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Public\Contracts;

interface SecuritySessionSettings
{
    public function inactivityTimeoutMinutes(): int;
}
