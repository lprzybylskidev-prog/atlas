<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

final readonly class DataLifecyclePreview
{
    /**
     * @param  list<DataLifecycleImpact>  $impacts
     * @param  list<DataLifecycleBlocker>  $blockers
     */
    public function __construct(
        public array $impacts,
        public array $blockers = [],
    ) {}

    public function canExecute(): bool
    {
        return $this->blockers === [];
    }
}
