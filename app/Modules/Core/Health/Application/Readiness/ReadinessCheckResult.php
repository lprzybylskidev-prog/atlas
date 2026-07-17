<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Application\Readiness;

final readonly class ReadinessCheckResult
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function __construct(
        public string $key,
        public string $label,
        public HealthCheckStatus $status,
        public bool $blocking,
        public string $description,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public static function healthy(string $key, string $label, bool $blocking, string $description, array $metadata = []): self
    {
        return new self($key, $label, HealthCheckStatus::Healthy, $blocking, $description, $metadata);
    }

    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public static function degraded(string $key, string $label, bool $blocking, string $description, array $metadata = []): self
    {
        return new self($key, $label, HealthCheckStatus::Degraded, $blocking, $description, $metadata);
    }

    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public static function unhealthy(string $key, string $label, bool $blocking, string $description, array $metadata = []): self
    {
        return new self($key, $label, HealthCheckStatus::Unhealthy, $blocking, $description, $metadata);
    }

    /**
     * @return array{key: string, label: string, status: string, blocking: bool, description: string, metadata: array<string, bool|int|string|null>}
     */
    public function toAdminArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status->value,
            'blocking' => $this->blocking,
            'description' => $this->description,
            'metadata' => $this->metadata,
        ];
    }
}
