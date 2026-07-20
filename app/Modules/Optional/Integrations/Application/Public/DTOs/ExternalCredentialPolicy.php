<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Public\DTOs;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ExternalCredentialPolicy
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $allowedModules
     */
    public function __construct(
        public string $clientKey,
        public array $scopes,
        public ?int $teamId = null,
        public ?CarbonImmutable $expiresAt = null,
        public array $allowedModules = [],
        public bool $externalApiEnabled = false,
    ) {
        if (trim($clientKey) === '') {
            throw new InvalidArgumentException('External client key must be non-empty.');
        }
    }
}
