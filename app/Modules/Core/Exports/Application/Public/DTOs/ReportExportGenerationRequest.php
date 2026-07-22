<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\DTOs;

use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use DateTimeImmutable;

final readonly class ReportExportGenerationRequest
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{id: string, desc: bool}>  $sorting
     * @param  list<string>  $visibleColumns
     * @param  list<string>  $columnOrder
     * @param  list<string>  $allowedColumns
     * @param  array<string, string|null>|null  $timeRange
     */
    public function __construct(
        public string $publicId,
        public string $reportKey,
        public string $reportName,
        public string $moduleKey,
        public ReportExportFormat $format,
        public ?string $activeTeamPublicId,
        public string $requestingUserPublicId,
        public array $filters,
        public array $sorting,
        public array $visibleColumns,
        public array $columnOrder,
        public array $allowedColumns,
        public ?array $timeRange,
        public string $releaseVersion,
        public string $ruleVersion,
        public DateTimeImmutable $expiresAt,
    ) {}
}
