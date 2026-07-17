<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final readonly class RateLimitPolicyRegistrar
{
    public function __construct(
        private RateLimitPolicyCatalog $catalog,
        private RateLimitKeyBuilder $keyBuilder,
        private RateLimitRejectionRecorder $rejections,
    ) {}

    public function register(): void
    {
        foreach ($this->catalog->all() as $policy) {
            RateLimiter::for($policy->name, function (Request $request) use ($policy): Limit {
                $key = $this->keyBuilder->build($request, $policy);

                return new Limit('', $policy->maxAttempts, $policy->decaySeconds)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($policy, $key): Response {
                        $requestId = $request->attributes->get('request_id');
                        $this->rejections->record($policy->name, $key, is_string($requestId) ? $requestId : null);

                        return response('Too Many Attempts.', Response::HTTP_TOO_MANY_REQUESTS, $headers);
                    });
            });
        }
    }
}
