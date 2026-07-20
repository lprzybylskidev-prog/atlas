<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\DTOs;

final readonly class ImportSource
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $type,
        public ?string $filePublicId,
        public ?string $apiReference,
        public ?string $externalReference,
        public array $metadata = [],
    ) {}
}
