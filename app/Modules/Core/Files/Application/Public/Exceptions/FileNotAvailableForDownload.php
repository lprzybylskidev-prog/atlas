<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Exceptions;

use RuntimeException;

final class FileNotAvailableForDownload extends RuntimeException
{
    public static function blocked(string $publicId): self
    {
        return new self(sprintf('File [%s] is not clean and cannot be downloaded.', $publicId));
    }
}
