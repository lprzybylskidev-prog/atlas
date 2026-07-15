<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

final readonly class DataLifecycleResult
{
    /**
     * @param  list<DataLifecycleStepResult>  $steps
     * @param  list<DataLifecycleBlocker>  $blockers
     */
    public function __construct(
        public array $steps,
        public array $blockers = [],
    ) {}

    public function completed(): bool
    {
        return $this->blockers === [];
    }
}
