<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Messages\Exceptions;

use RuntimeException;

final class StaleMessageEdit extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The message changed after it was opened. Reload it before saving.');
    }
}
