<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Identity\Application\RateLimiting\Exceptions\InvalidRateLimitPolicy;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitKeyBuilder;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitKeyPart;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicy;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class IdentityRateLimitPolicyTest extends TestCase
{
    public function test_configured_rate_limit_catalog_contains_all_required_stable_policies(): void
    {
        $configured = require __DIR__.'/../../../config/atlas.php';
        self::assertIsArray($configured);

        $catalog = RateLimitPolicyCatalog::fromConfiguredValue($this->configuredPolicies($configured));

        self::assertSame(
            RateLimitPolicyCatalog::REQUIRED_POLICIES,
            array_map(static fn (RateLimitPolicy $policy): string => $policy->name, $catalog->all()),
        );

        self::assertTrue($catalog->get(RateLimitPolicyCatalog::AUTH_LOGIN)->supportsProgressiveDelay());
        self::assertTrue($catalog->get(RateLimitPolicyCatalog::AUTH_LOGIN)->supportsTemporaryLock());
        self::assertTrue($catalog->get(RateLimitPolicyCatalog::AUTH_MFA)->supportsTemporaryLock());
    }

    public function test_rate_limit_policy_rejects_missing_required_policies(): void
    {
        $this->expectException(InvalidRateLimitPolicy::class);
        $this->expectExceptionMessage('Rate-limit policy [auth.login] is not configured.');

        new RateLimitPolicyCatalog([])->all();
    }

    public function test_rate_limit_key_builder_supports_user_ip_team_and_api_client_parts(): void
    {
        $request = Request::create('/login', 'POST', ['email' => 'USER@example.test']);
        $request->headers->set('X-Atlas-Team', 'team-01');
        $request->headers->set('X-Atlas-Api-Client', 'client-01');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        $policy = new RateLimitPolicy(
            name: 'api.sensitive',
            maxAttempts: 10,
            decaySeconds: 60,
            keyParts: [
                RateLimitKeyPart::ApiClient,
                RateLimitKeyPart::User,
                RateLimitKeyPart::Team,
                RateLimitKeyPart::Ip,
            ],
        );

        self::assertSame(
            'api.sensitive|api-client:client-01|user:user@example.test|team:team-01|ip:203.0.113.10',
            new RateLimitKeyBuilder()->build($request, $policy),
        );
    }

    /**
     * @param  array<mixed, mixed>  $configured
     */
    private function configuredPolicies(array $configured): mixed
    {
        $security = $configured['security'] ?? null;

        if (! is_array($security)) {
            return null;
        }

        $rateLimits = $security['rate_limits'] ?? null;

        if (! is_array($rateLimits)) {
            return null;
        }

        return $rateLimits['policies'] ?? null;
    }
}
