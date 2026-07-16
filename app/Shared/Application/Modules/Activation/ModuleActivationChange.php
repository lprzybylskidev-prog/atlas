<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

use Carbon\CarbonImmutable;

final readonly class ModuleActivationChange
{
    public function __construct(
        public string $moduleKey,
        public ModuleActivationScope $scope,
        public bool $enabled,
        public string $reason,
        public ?int $actorUserId = null,
        public ?int $teamId = null,
        public ?int $expectedVersion = null,
        public ModuleActivationSource $source = ModuleActivationSource::Manual,
        public ?int $scheduleId = null,
        public ?CarbonImmutable $effectiveAt = null,
    ) {}
}
