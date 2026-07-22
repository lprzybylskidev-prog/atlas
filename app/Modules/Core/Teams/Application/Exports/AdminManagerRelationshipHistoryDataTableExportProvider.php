<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminManagerRelationshipHistoryDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ManagerHierarchy $hierarchy) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::MANAGER_RELATIONSHIP_HISTORY;
    }

    public function tableName(): string
    {
        return 'Manager relationship history';
    }

    public function owningModuleKey(): string
    {
        return 'teams';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-manager-relationship-history-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'teamPublicId' => 'Team public ID',
            'teamName' => 'Team',
            'managerUserPublicId' => 'Manager public ID',
            'managerName' => 'Manager',
            'managerEmail' => 'Manager email',
            'reportUserPublicId' => 'Report public ID',
            'reportName' => 'Direct report',
            'reportEmail' => 'Report email',
            'validFrom' => 'Valid from',
            'validTo' => 'Valid to',
            'reason' => 'Reason',
            'endReason' => 'End reason',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $teamPublicId = self::filterValue($request, 'team');

        if ($teamPublicId === '') {
            return;
        }

        $rows = array_map(static fn ($relationship): array => [
            'publicId' => $relationship->publicId,
            'teamPublicId' => $relationship->teamPublicId,
            'teamName' => $relationship->teamName,
            'managerUserPublicId' => $relationship->managerUserPublicId,
            'managerName' => $relationship->managerName,
            'managerEmail' => $relationship->managerEmail,
            'reportUserPublicId' => $relationship->reportUserPublicId,
            'reportName' => $relationship->reportName,
            'reportEmail' => $relationship->reportEmail,
            'validFrom' => $relationship->validFrom,
            'validTo' => $relationship->validTo,
            'reason' => $relationship->reason,
            'endReason' => $relationship->endReason,
        ], $this->hierarchy->relationshipHistory($teamPublicId));

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
            yield $row;
        }
    }
}
