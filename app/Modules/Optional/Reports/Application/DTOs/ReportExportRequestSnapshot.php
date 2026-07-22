<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use DateTimeImmutable;

final readonly class ReportExportRequestSnapshot
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{id: string, desc: bool}>  $sorting
     * @param  list<string>  $visibleColumns
     * @param  list<string>  $columnOrder
     * @param  array<string, string|null>|null  $timeRange
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
        public array $visibleColumns,
        public array $columnOrder,
        public ?array $timeRange,
        public AuthorizationFingerprint $authorization,
        public string $releaseVersion,
        public string $ruleVersion,
        public DateTimeImmutable $expiresAt,
        public bool $synchronousAllowed = false,
        public bool $auditExport = false,
        public ?int $estimatedRowCount = null,
    ) {}

    public function requestFingerprint(): string
    {
        return hash('sha256', json_encode([
            'report_key' => $this->reportKey,
            'module_key' => $this->moduleKey,
            'format' => $this->format->value,
            'active_team_public_id' => $this->activeTeamPublicId,
            'requesting_user_public_id' => $this->requestingUserPublicId,
            'filters' => $this->filters,
            'sorting' => $this->sorting,
            'visible_columns' => $this->visibleColumns,
            'column_order' => $this->columnOrder,
            'time_range' => $this->timeRange,
            'authorization_fingerprint' => $this->authorization->hash(),
            'release_version' => $this->releaseVersion,
            'rule_version' => $this->ruleVersion,
            'audit_export' => $this->auditExport,
            'estimated_row_count' => $this->estimatedRowCount,
        ], JSON_THROW_ON_ERROR));
    }
}
