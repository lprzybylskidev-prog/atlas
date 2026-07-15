<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use App\Modules\Core\Identity\Application\RateLimiting\Exceptions\InvalidRateLimitPolicy;

final readonly class RateLimitPolicy
{
    /**
     * @param  list<RateLimitKeyPart>  $keyParts
     * @param  list<int>  $progressiveDelaySeconds
     */
    public function __construct(
        public string $name,
        public int $maxAttempts,
        public int $decaySeconds,
        public array $keyParts,
        public array $progressiveDelaySeconds = [],
        public ?int $temporaryLockSeconds = null,
    ) {
        if (trim($name) === '') {
            throw InvalidRateLimitPolicy::invalid($name, 'name must be a non-empty string.');
        }

        if ($maxAttempts < 1) {
            throw InvalidRateLimitPolicy::invalid($name, 'max attempts must be greater than zero.');
        }

        if ($decaySeconds < 1) {
            throw InvalidRateLimitPolicy::invalid($name, 'decay seconds must be greater than zero.');
        }

        if ($keyParts === []) {
            throw InvalidRateLimitPolicy::invalid($name, 'at least one key part is required.');
        }

        foreach ($progressiveDelaySeconds as $delaySeconds) {
            if ($delaySeconds < 1) {
                throw InvalidRateLimitPolicy::invalid($name, 'progressive delays must be greater than zero.');
            }
        }

        if ($temporaryLockSeconds !== null && $temporaryLockSeconds < 1) {
            throw InvalidRateLimitPolicy::invalid($name, 'temporary lock seconds must be greater than zero when provided.');
        }
    }

    public function supportsProgressiveDelay(): bool
    {
        return $this->progressiveDelaySeconds !== [];
    }

    public function supportsTemporaryLock(): bool
    {
        return $this->temporaryLockSeconds !== null;
    }
}
