<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\DTOs;

readonly class SearchHit
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public string $publicId,
        public string $moduleKey,
        public array $fields,
    ) {}
}
