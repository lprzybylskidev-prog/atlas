<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Exceptions;

use RuntimeException;

final class ReportRenderCredentialInvalid extends RuntimeException
{
    public static function blocked(): self
    {
        return new self('Report render credential is not valid.');
    }
}
