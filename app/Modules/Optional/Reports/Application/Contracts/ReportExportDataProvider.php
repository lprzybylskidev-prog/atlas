<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportColumn;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportGenerationRequest;

interface ReportExportDataProvider
{
    public function reportKey(): string;

    /**
     * @return list<ReportExportColumn>
     */
    public function columns(ReportExportGenerationRequest $request): array;

    /**
     * @return iterable<array<string, scalar|\Stringable|\DateTimeInterface|null>>
     */
    public function rows(ReportExportGenerationRequest $request): iterable;
}
