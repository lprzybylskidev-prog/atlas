<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Optional\Reports\Application\Contracts\ReportRenderReadinessProbe;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;
use App\Modules\Optional\Reports\Application\Exceptions\ReportRenderVisualsNotReady;

final readonly class ReportRenderReadinessRegistry
{
    /**
     * @param  iterable<ReportRenderReadinessProbe>  $probes
     */
    public function __construct(private iterable $probes) {}

    public function assertReady(ReportExportGenerationRequest $request): void
    {
        foreach ($this->probes as $probe) {
            if ($probe->reportKey() !== $request->reportKey) {
                continue;
            }

            $result = $probe->check($request);

            if (! $result->ready) {
                throw ReportRenderVisualsNotReady::blocked(
                    reportKey: $request->reportKey,
                    safeSummary: $result->safeSummary ?? 'required visual did not report readiness',
                );
            }
        }
    }
}
