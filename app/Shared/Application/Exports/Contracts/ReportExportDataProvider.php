<?php

declare(strict_types=1);

namespace App\Shared\Application\Exports\Contracts;

use App\Shared\Application\Exports\DTOs\ReportExportColumn;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;

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
