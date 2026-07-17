<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Application\Readiness\Contracts;

use App\Modules\Core\Health\Application\Readiness\ReadinessReport;

interface ReadinessChecker
{
    public function check(): ReadinessReport;
}
