<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class StartCallResult
{
    public function __construct(
        public CallSnapshot $call,
        public bool $created,
    ) {}
}
