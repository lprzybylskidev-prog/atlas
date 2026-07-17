<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Presentation\Http\Controllers;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use Illuminate\Http\JsonResponse;

final readonly class ReadinessController
{
    public function __construct(private ReadinessChecker $readiness) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->readiness->check();

        return response()->json($report->toPublicArray(), $report->httpStatus());
    }
}
