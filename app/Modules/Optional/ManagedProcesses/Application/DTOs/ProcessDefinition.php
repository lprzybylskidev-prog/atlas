<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\DTOs;

final readonly class ProcessDefinition
{
    /**
     * @param  array<string, mixed>|null  $inputSchema
     */
    public function __construct(
        public string $key,
        public string $moduleKey,
        public string $label,
        public string $description,
        public string $scope,
        public ?array $inputSchema,
        public ProcessPermissions $permissions,
        public string $queueName,
        public string $executionMode,
        public string $concurrencyPolicy,
        public int $parallelism,
        public RetryPolicy $retryPolicy,
        public string $cancellationPolicy,
        public bool $scheduleSupported,
        public bool $manualStartSupported,
        public bool $externalEffects = false,
        public bool $highRisk = false,
        public bool $blocksModuleDeactivation = true,
    ) {}
}
