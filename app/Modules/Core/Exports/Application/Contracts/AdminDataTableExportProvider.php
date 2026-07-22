<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\AdminDataTableExportContext;
use App\Shared\Application\Tables\TableDefinition;

interface AdminDataTableExportProvider extends ReportExportDataProvider
{
    public function tableKey(): string;

    public function tableName(): string;

    public function owningModuleKey(): string;

    public function requestPermission(): string;

    public function ruleVersion(): string;

    public function tableDefinition(): TableDefinition;

    /**
     * @return list<string>
     */
    public function allowedExportColumns(AdminDataTableExportContext $context): array;

    /**
     * @return list<ReportExportFormat>
     */
    public function supportedFormats(AdminDataTableExportContext $context): array;
}
