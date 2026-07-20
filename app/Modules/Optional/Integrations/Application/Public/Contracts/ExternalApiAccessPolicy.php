<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\Contracts;

use App\Modules\Optional\Integrations\Application\Public\DTOs\ExternalCredentialPolicy;

interface ExternalApiAccessPolicy
{
    public function assertExternalApiEnabled(ExternalCredentialPolicy $policy, string $moduleKey, string $scope): void;
}
