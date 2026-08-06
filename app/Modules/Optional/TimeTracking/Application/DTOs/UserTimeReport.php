<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class UserTimeReport
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{totalSeconds: int, workSeconds: int, breakSeconds: int, otherWorkSeconds: int, corrections: int, pending: int}  $summary
     * @param  array{range: string, from: string, to: string, type: string, status: string, module: string, compare: string}  $filters
     * @param  array{available: bool, rangeLabel: string, previousRangeLabel: string, metrics: list<array{metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>, userMetrics: list<array{userPublicId: string, userName: string, metric: string, currentSeconds: int, previousSeconds: int, deltaSeconds: int, percentDelta: float|null}>}|null  $comparison
     */
    public function __construct(
        public array $rows,
        public array $summary,
        public array $filters,
        public ?array $comparison = null,
    ) {}
}
