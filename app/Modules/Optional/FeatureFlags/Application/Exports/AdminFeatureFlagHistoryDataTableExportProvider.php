<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminFeatureFlagHistoryDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private FeatureFlagStore $store) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::FEATURE_FLAG_HISTORY;
    }

    public function tableName(): string
    {
        return 'Feature flag history';
    }

    public function owningModuleKey(): string
    {
        return 'feature_flags';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-feature-flag-history-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'createdAt' => 'Changed',
            'flagKey' => 'Flag',
            'scope' => 'Scope',
            'teamName' => 'Team',
            'teamPublicId' => 'Team public ID',
            'action' => 'Action',
            'reason' => 'Reason',
            'actorPublicId' => 'Actor',
            'beforeEnabled' => 'Before enabled',
            'afterEnabled' => 'After enabled',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(static fn (array $row): array => [
            'publicId' => self::stringValue($row['publicId'] ?? null),
            'createdAt' => self::stringValue($row['createdAt'] ?? null),
            'flagKey' => self::stringValue($row['flagKey'] ?? null),
            'scope' => self::stringValue($row['scope'] ?? null),
            'teamName' => self::stringValue($row['teamName'] ?? null),
            'teamPublicId' => self::stringValue($row['teamPublicId'] ?? null),
            'action' => self::stringValue($row['action'] ?? null),
            'reason' => self::stringValue($row['reason'] ?? null),
            'actorPublicId' => self::stringValue($row['actorPublicId'] ?? null),
            'beforeEnabled' => is_array($row['before'] ?? null) ? (bool) ($row['before']['enabled'] ?? false) : null,
            'afterEnabled' => is_array($row['after'] ?? null) ? (bool) ($row['after']['enabled'] ?? false) : null,
        ], $this->store->recentHistory());

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
