<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final readonly class RateLimitPolicyRegistrar
{
    public function __construct(
        private RateLimitPolicyCatalog $catalog,
        private RateLimitKeyBuilder $keyBuilder,
    ) {}

    public function register(): void
    {
        foreach ($this->catalog->all() as $policy) {
            RateLimiter::for($policy->name, function (Request $request) use ($policy): Limit {
                return new Limit('', $policy->maxAttempts, $policy->decaySeconds)
                    ->by($this->keyBuilder->build($request, $policy));
            });
        }
    }
}
