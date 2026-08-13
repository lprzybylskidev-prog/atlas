<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Messages\Exceptions;

use RuntimeException;

final class MessageIdempotencyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The message idempotency key was already used for different content.');
    }
}
