<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

use App\Modules\Core\Files\Application\Public\Enums\FileScanState;

interface FileScanner
{
    public function scanNow(string $publicId): FileScanState;
}
