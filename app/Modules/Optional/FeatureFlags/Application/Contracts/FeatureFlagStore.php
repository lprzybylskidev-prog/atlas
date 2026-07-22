<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Contracts;

use App\Modules\Optional\FeatureFlags\Application\DTOs\FeatureFlagState;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;

interface FeatureFlagStore
{
    public function state(FeatureFlagKey|string $key, ?string $teamPublicId = null): FeatureFlagState;

    public function setGlobal(FeatureFlagKey|string $key, bool $enabled, string $actorPublicId, string $reason): void;

    public function setTeam(FeatureFlagKey|string $key, string $teamPublicId, bool $enabled, string $actorPublicId, string $reason): void;

    public function clearTeam(FeatureFlagKey|string $key, string $teamPublicId, string $actorPublicId, string $reason): void;

    /**
     * @return list<array<string, scalar|null|array<string, mixed>>>
     */
    public function recentHistory(int $limit = 50): array;
}
