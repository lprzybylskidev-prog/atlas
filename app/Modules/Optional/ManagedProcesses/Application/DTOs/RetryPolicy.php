<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\DTOs;

use App\Shared\Application\ManagedProcesses\DTOs\RetryPolicy as PublicRetryPolicy;

final readonly class RetryPolicy extends PublicRetryPolicy {}
