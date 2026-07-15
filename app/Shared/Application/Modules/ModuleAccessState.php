<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final readonly class ModuleAccessState
{
    public function __construct(
        public bool $deployed,
        public bool $requiredDependenciesSatisfied,
        public bool $technicallyAvailable,
        public bool $globallyActive,
        public bool $teamActive,
        public bool $activeTeamValid,
        public bool $permissionGranted,
    ) {}
}
