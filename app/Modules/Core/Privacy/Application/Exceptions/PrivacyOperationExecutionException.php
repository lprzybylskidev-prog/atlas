<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Exceptions;

use RuntimeException;

final class PrivacyOperationExecutionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorKey,
    ) {
        parent::__construct($errorKey);
    }
}
