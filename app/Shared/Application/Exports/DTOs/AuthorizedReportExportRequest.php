<?php

declare(strict_types=1);

namespace App\Shared\Application\Exports\DTOs;

use App\Shared\Application\Exports\Enums\ReportExportFormat;
use DateTimeImmutable;

final readonly class AuthorizedReportExportRequest
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{id:string,desc:bool}>  $sorting
     * @param  list<string>  $columns
     * @param  list<string>  $permissionNames
     */
    public function __construct(
        public string $reportKey,
        public string $reportName,
        public string $moduleKey,
        public ReportExportFormat $format,
        public ?int $activeTeamId,
        public ?string $activeTeamPublicId,
        public int $requestingUserId,
        public string $requestingUserPublicId,
        public array $filters,
        public array $sorting,
        public array $columns,
        public array $permissionNames,
        public string $ruleVersion,
        public string $releaseVersion,
        public DateTimeImmutable $expiresAt,
        public string $locale,
    ) {}
}
