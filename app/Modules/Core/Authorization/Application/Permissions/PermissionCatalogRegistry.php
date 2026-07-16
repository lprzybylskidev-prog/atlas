<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;
use RuntimeException;

final readonly class PermissionCatalogRegistry
{
    /**
     * @param  iterable<ModulePermissionContribution>  $catalogs
     */
    public function __construct(
        private iterable $catalogs,
    ) {}

    /**
     * @return list<ModulePermissionDefinition>
     */
    public function all(): array
    {
        $permissions = [];
        $seen = [];

        foreach ($this->catalogs as $catalog) {
            foreach ($catalog->permissions() as $permission) {
                if (isset($seen[$permission->name])) {
                    throw new RuntimeException(sprintf('Permission [%s] is declared more than once.', $permission->name));
                }

                $seen[$permission->name] = true;
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(
            static fn (ModulePermissionDefinition $permission): string => $permission->name,
            $this->all(),
        );
    }
}
