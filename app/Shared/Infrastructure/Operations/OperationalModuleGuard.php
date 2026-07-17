<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessRequest;
use RuntimeException;

final readonly class OperationalModuleGuard
{
    public function __construct(private ModuleGate $moduleGate) {}

    public function ensureAllowed(string $moduleKey, ?string $activeTeamPublicId = null, ?string $userPublicId = null, ?string $permission = null): void
    {
        $decision = $this->moduleGate->inspect(new ModuleAccessRequest(
            moduleKey: $moduleKey,
            activeTeamPublicId: $activeTeamPublicId,
            userPublicId: $userPublicId,
            requiredPermission: $permission,
        ));

        if (! $decision->allowed) {
            throw new RuntimeException(sprintf('Module [%s] is not available for this operational action.', $moduleKey));
        }
    }

    public function moduleFromClassName(string $className): string
    {
        $normalized = str_replace('/', '\\', $className);

        if (preg_match('/^App\\\\Modules\\\\Core\\\\([^\\\\]+)\\\\/', $normalized, $matches) === 1) {
            return str((string) $matches[1])->kebab()->toString();
        }

        if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\/', $normalized, $matches) === 1) {
            return str((string) $matches[1])->kebab()->toString();
        }

        return 'authorization';
    }
}
