<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\DTOs;

final readonly class ParsedImportRow
{
    /**
     * @param  array<string, scalar|null>  $values
     */
    public function __construct(
        public int $rowNumber,
        public array $values,
    ) {}
}
