<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

final readonly class EffectiveModuleState
{
    public function __construct(
        public string $moduleKey,
        public bool $deployed,
        public bool $technicallyAvailable,
        public bool $globallyEnabled,
        public bool $teamEnabled,
        public bool $effectiveEnabled,
        public string $source,
        public ?string $teamPublicId = null,
        public ?string $reason = null,
        public ?int $globalVersion = null,
        public ?int $teamVersion = null,
    ) {}
}
