<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Exceptions;

use RuntimeException;

final class ExternalApiAccessDisabled extends RuntimeException
{
    public static function forScope(string $moduleKey, string $scope): self
    {
        return new self(sprintf('External API access is disabled for module [%s] and scope [%s].', $moduleKey, $scope));
    }
}
