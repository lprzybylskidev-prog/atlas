<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Application\Readiness;

use Carbon\CarbonImmutable;

final readonly class ReadinessReport
{
    /**
     * @param  list<ReadinessCheckResult>  $checks
     */
    public function __construct(
        public array $checks,
        public CarbonImmutable $checkedAt,
    ) {}

    public function status(): HealthCheckStatus
    {
        if ($this->blockingFailureCount() > 0) {
            return HealthCheckStatus::Unhealthy;
        }

        if ($this->degradedFailureCount() > 0) {
            return HealthCheckStatus::Degraded;
        }

        return HealthCheckStatus::Healthy;
    }

    public function httpStatus(): int
    {
        return $this->status() === HealthCheckStatus::Unhealthy ? 503 : 200;
    }

    public function blockingFailureCount(): int
    {
        return count(array_filter(
            $this->checks,
            static fn (ReadinessCheckResult $check): bool => $check->blocking && $check->status !== HealthCheckStatus::Healthy,
        ));
    }

    public function degradedFailureCount(): int
    {
        return count(array_filter(
            $this->checks,
            static fn (ReadinessCheckResult $check): bool => ! $check->blocking && $check->status !== HealthCheckStatus::Healthy,
        ));
    }

    /**
     * @return array{status: string, checked_at: string, release: array{version: string, id: string}, blocking: array{failed: int, total: int}, degraded: array{failed: int, total: int}}
     */
    public function toPublicArray(): array
    {
        return [
            'status' => $this->status()->value,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'release' => [
                'version' => config()->string('atlas.release.version'),
                'id' => config()->string('atlas.release.id'),
            ],
            'blocking' => [
                'failed' => $this->blockingFailureCount(),
                'total' => $this->blockingCount(),
            ],
            'degraded' => [
                'failed' => $this->degradedFailureCount(),
                'total' => $this->degradedCount(),
            ],
        ];
    }

    /**
     * @return array{status: string, checked_at: string, release: array{version: string, id: string}, blocking: array{failed: int, total: int}, degraded: array{failed: int, total: int}, checks: list<array{key: string, label: string, status: string, blocking: bool, description: string, metadata: array<string, bool|int|string|null>}>}
     */
    public function toAdminArray(): array
    {
        return [
            ...$this->toPublicArray(),
            'checks' => array_map(
                static fn (ReadinessCheckResult $check): array => $check->toAdminArray(),
                $this->checks,
            ),
        ];
    }

    private function blockingCount(): int
    {
        return count(array_filter($this->checks, static fn (ReadinessCheckResult $check): bool => $check->blocking));
    }

    private function degradedCount(): int
    {
        return count(array_filter($this->checks, static fn (ReadinessCheckResult $check): bool => ! $check->blocking));
    }
}
