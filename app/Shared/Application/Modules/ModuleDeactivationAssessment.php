<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final readonly class ModuleDeactivationAssessment
{
    /**
     * @param  list<ModuleDeactivationBlocker>  $blockers
     * @param  list<ModuleDeactivationSafeAction>  $safeActions
     */
    public function __construct(
        public array $blockers,
        public array $safeActions = [],
    ) {}

    public static function allow(): self
    {
        return new self(blockers: []);
    }

    /**
     * @param  list<ModuleDeactivationSafeAction>  $safeActions
     */
    public static function block(ModuleDeactivationBlocker $blocker, array $safeActions = []): self
    {
        return new self(blockers: [$blocker], safeActions: $safeActions);
    }

    public function canDeactivate(): bool
    {
        return $this->blockers === [];
    }
}
