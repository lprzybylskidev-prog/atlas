<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\DTOs;

use InvalidArgumentException;

final readonly class IntegrationDefinition
{
    /**
     * @param  list<string>  $providedScopes
     * @param  list<string>  $requiredModules
     * @param  list<string>  $optionalModules
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $adapterClass,
        public string $sourceOfTruth,
        public array $providedScopes = [],
        public array $requiredModules = [],
        public array $optionalModules = [],
        public bool $externalApiEnabled = false,
    ) {
        if (trim($key) === '' || trim($name) === '' || trim($adapterClass) === '' || trim($sourceOfTruth) === '') {
            throw new InvalidArgumentException('Integration definition fields must be non-empty strings.');
        }
    }
}
