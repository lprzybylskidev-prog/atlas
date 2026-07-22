<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportExportColumn;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;

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
