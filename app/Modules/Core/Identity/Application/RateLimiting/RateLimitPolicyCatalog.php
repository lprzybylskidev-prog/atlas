<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

use App\Modules\Core\Identity\Application\RateLimiting\Exceptions\InvalidRateLimitPolicy;

final readonly class RateLimitPolicyCatalog
{
    public const AUTH_LOGIN = 'auth.login';

    public const AUTH_PASSWORD_RESET = 'auth.password-reset';

    public const AUTH_MFA = 'auth.mfa';

    public const API_DEFAULT = 'api.default';

    public const API_SENSITIVE = 'api.sensitive';

    public const EXPORTS_CREATE = 'exports.create';

    public const IMPORTS_CREATE = 'imports.create';

    public const ADMIN_HIGH_RISK = 'admin.high-risk';

    public const REQUIRED_POLICIES = [
        self::AUTH_LOGIN,
        self::AUTH_PASSWORD_RESET,
        self::AUTH_MFA,
        self::API_DEFAULT,
        self::API_SENSITIVE,
        self::EXPORTS_CREATE,
        self::IMPORTS_CREATE,
        self::ADMIN_HIGH_RISK,
    ];

    /**
     * @param  array<string, mixed>  $configuredPolicies
     */
    public function __construct(private array $configuredPolicies) {}

    public static function fromConfiguredValue(mixed $configuredPolicies): self
    {
        if (! is_array($configuredPolicies)) {
            return new self([]);
        }

        $normalized = [];

        foreach ($configuredPolicies as $name => $policy) {
            if (! is_string($name)) {
                throw InvalidRateLimitPolicy::invalid('unknown', 'configured policy names must be strings.');
            }

            $normalized[$name] = $policy;
        }

        return new self($normalized);
    }

    /**
     * @return list<RateLimitPolicy>
     */
    public function all(): array
    {
        return array_map(
            fn (string $policyName): RateLimitPolicy => $this->get($policyName),
            self::REQUIRED_POLICIES,
        );
    }

    public function get(string $name): RateLimitPolicy
    {
        $configured = $this->configuredPolicies[$name] ?? null;

        if (! is_array($configured)) {
            throw InvalidRateLimitPolicy::missing($name);
        }

        $configured = $this->stringKeyedArray($name, $configured);

        return new RateLimitPolicy(
            name: $name,
            maxAttempts: $this->positiveInt($name, $configured, 'max_attempts'),
            decaySeconds: $this->positiveInt($name, $configured, 'decay_seconds'),
            keyParts: $this->keyParts($name, $configured),
            progressiveDelaySeconds: $this->positiveIntList($name, $configured, 'progressive_delay_seconds'),
            temporaryLockSeconds: $this->nullablePositiveInt($name, $configured, 'temporary_lock_seconds'),
        );
    }

    /**
     * @param  array<mixed, mixed>  $configured
     * @return array<string, mixed>
     */
    private function stringKeyedArray(string $policyName, array $configured): array
    {
        $normalized = [];

        foreach ($configured as $key => $value) {
            if (! is_string($key)) {
                throw InvalidRateLimitPolicy::invalid($policyName, 'policy fields must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $configured
     */
    private function positiveInt(string $policyName, array $configured, string $field): int
    {
        $value = $configured[$field] ?? null;

        if (! is_int($value) || $value < 1) {
            throw InvalidRateLimitPolicy::invalid($policyName, sprintf('[%s] must be a positive integer.', $field));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $configured
     */
    private function nullablePositiveInt(string $policyName, array $configured, string $field): ?int
    {
        $value = $configured[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 1) {
            throw InvalidRateLimitPolicy::invalid($policyName, sprintf('[%s] must be null or a positive integer.', $field));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $configured
     * @return list<int>
     */
    private function positiveIntList(string $policyName, array $configured, string $field): array
    {
        $value = $configured[$field] ?? [];

        if (! is_array($value) || $value !== array_values($value)) {
            throw InvalidRateLimitPolicy::invalid($policyName, sprintf('[%s] must be a list of positive integers.', $field));
        }

        foreach ($value as $item) {
            if (! is_int($item) || $item < 1) {
                throw InvalidRateLimitPolicy::invalid($policyName, sprintf('[%s] must contain only positive integers.', $field));
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $configured
     * @return list<RateLimitKeyPart>
     */
    private function keyParts(string $policyName, array $configured): array
    {
        $value = $configured['key'] ?? null;

        if (! is_array($value) || $value === [] || $value !== array_values($value)) {
            throw InvalidRateLimitPolicy::invalid($policyName, '[key] must be a non-empty list.');
        }

        return array_map(
            static function (mixed $part) use ($policyName): RateLimitKeyPart {
                if (! is_string($part)) {
                    throw InvalidRateLimitPolicy::invalid($policyName, '[key] entries must be strings.');
                }

                return RateLimitKeyPart::tryFrom($part)
                    ?? throw InvalidRateLimitPolicy::invalid($policyName, sprintf('unknown key part [%s].', $part));
            },
            $value,
        );
    }
}
