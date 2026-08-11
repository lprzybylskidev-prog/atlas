<?php

declare(strict_types=1);

namespace App\Shared\Application\Files\Contracts;

interface FileAvailability
{
    public function clean(string $publicId): bool;
}
