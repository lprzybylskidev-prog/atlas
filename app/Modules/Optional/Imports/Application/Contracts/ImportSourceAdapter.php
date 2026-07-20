<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Contracts;

use App\Modules\Optional\Imports\Application\DTOs\ImportSource;
use App\Modules\Optional\Imports\Application\DTOs\ParsedImportRow;

interface ImportSourceAdapter
{
    public function sourceType(): string;

    /**
     * @return list<ParsedImportRow>
     */
    public function parse(ImportSource $source): array;
}
