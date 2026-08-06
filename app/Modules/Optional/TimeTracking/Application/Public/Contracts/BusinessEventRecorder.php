<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\Contracts;

use App\Modules\Optional\TimeTracking\Application\Public\DTOs\BusinessEventInput;

interface BusinessEventRecorder
{
    public function record(BusinessEventInput $event): void;
}
