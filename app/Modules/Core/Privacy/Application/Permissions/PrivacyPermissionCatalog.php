<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class PrivacyPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_INDEX = 'admin.privacy-retention.index';

    public const HARD_DELETE_PREVIEW = 'admin.privacy-retention.hard-delete.preview';

    public const HARD_DELETE_EXECUTE = 'admin.privacy-retention.hard-delete.execute';

    public const ANONYMIZATION_PREVIEW = 'admin.privacy-retention.anonymization.preview';

    public const ANONYMIZATION_EXECUTE = 'admin.privacy-retention.anonymization.execute';

    public const LEGAL_HOLDS_INDEX = 'admin.privacy-retention.legal-holds.index';

    public const LEGAL_HOLDS_CREATE = 'admin.privacy-retention.legal-holds.create';

    public const LEGAL_HOLDS_STORE = 'admin.privacy-retention.legal-holds.store';

    public const OPERATIONS_INDEX = 'admin.privacy-retention.operations.index';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View privacy, retention, hard-delete, and anonymization readiness.'),
            new ModulePermissionDefinition(self::HARD_DELETE_PREVIEW, 'Preview high-risk hard-delete impact before execution.'),
            new ModulePermissionDefinition(self::HARD_DELETE_EXECUTE, 'Execute high-risk hard-delete workflows after confirmation and audit.'),
            new ModulePermissionDefinition(self::ANONYMIZATION_PREVIEW, 'Preview irreversible anonymization impact before execution.'),
            new ModulePermissionDefinition(self::ANONYMIZATION_EXECUTE, 'Execute irreversible anonymization workflows after confirmation and audit.'),
            new ModulePermissionDefinition(self::LEGAL_HOLDS_INDEX, 'View legal holds and retention blockers for privacy workflows.'),
            new ModulePermissionDefinition(self::LEGAL_HOLDS_CREATE, 'Open legal hold creation forms for privacy workflows.'),
            new ModulePermissionDefinition(self::LEGAL_HOLDS_STORE, 'Create legal holds and retention blockers for privacy workflows.'),
            new ModulePermissionDefinition(self::OPERATIONS_INDEX, 'View privacy operation request and preview history.'),
        ];
    }
}
