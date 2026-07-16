<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;

final readonly class AdministratorRoleUpdater
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private PermissionRoleStore $store,
        private SecurityAuditRecorder $audit,
    ) {}

    public function diff(): AdministratorRolePermissionDiff
    {
        $registered = $this->permissions->names();
        $existing = $this->store->rolePermissionNames(StarterRoleName::Administrator->value);

        return new AdministratorRolePermissionDiff(
            missingPermissionNames: array_values(array_diff($registered, $existing)),
            existingPermissionNames: $existing,
        );
    }

    public function apply(?string $actorPublicId, string $reason): AdministratorRolePermissionDiff
    {
        $this->store->ensurePermissions($this->permissions->all());

        $diff = $this->diff();

        if ($diff->hasMissingPermissions()) {
            $this->store->grantPermissionsToRole(
                StarterRoleName::Administrator->value,
                $diff->missingPermissionNames,
            );
        }

        $this->audit->record(new SecurityAuditEvent(
            module: 'authorization',
            action: 'authorization.administrator_role_update',
            result: 'succeeded',
            source: 'cli',
            actorPublicId: $actorPublicId,
            targetPublicId: null,
            reason: $reason,
            metadata: [
                'missing_count' => count($diff->missingPermissionNames),
            ],
        ));

        return $diff;
    }
}
