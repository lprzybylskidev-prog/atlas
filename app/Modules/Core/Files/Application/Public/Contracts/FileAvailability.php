<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

interface FileAvailability
{
    public function clean(string $publicId): bool;
}
