<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\State\Scope;

final class ObservabilityContext
{
    /**
     * @param  array<string, string|null>  $extra
     * @return array<string, string>
     */
    public function apply(
        string $source,
        string $eventName,
        ?string $module = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        array $extra = [],
    ): array {
        $context = [
            'correlation_id' => $correlationId ?? $this->existingContextString('correlation_id') ?? (string) Str::ulid(),
            'environment' => config()->string('app.env'),
            'release_version' => config()->string('atlas.release.version'),
            'release_id' => config()->string('atlas.release.id'),
            'source' => $source,
            'event_name' => $eventName,
            'module' => $module ?? $this->existingContextString('module') ?? 'shared',
        ];

        if ($causationId !== null) {
            $context['causation_id'] = $causationId;
        }

        foreach (['request_id', 'actor_public_id', 'team_public_id'] as $key) {
            $value = $this->existingContextString($key);

            if ($value !== null) {
                $context[$key] = $value;
            }
        }

        foreach ($extra as $key => $value) {
            if ($value !== null && $value !== '') {
                $context[$key] = $value;
            }
        }

        Context::add($context);
        Log::withContext($context);
        $this->configureSentryScope($context);

        return $context;
    }

    public function moduleFromClassName(string $className): string
    {
        $normalized = str_replace('/', '\\', $className);

        if (preg_match('/^App\\\\Modules\\\\Core\\\\([^\\\\]+)\\\\/', $normalized, $matches) === 1) {
            return Str::of($matches[1])->kebab()->toString();
        }

        return 'shared';
    }

    public function moduleFromCommandName(string $command): string
    {
        $prefix = Str::before($command, ':');

        return match ($prefix) {
            'auth', 'password', 'fortify' => 'identity',
            'module', 'modules' => 'teams',
            'notifications', 'notification' => 'notifications',
            'settings', 'setting' => 'settings',
            'audit' => 'audit',
            default => 'shared',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function propagatedQueueContext(array $payload): array
    {
        $context = $payload['illuminate:log:context'] ?? null;

        if (! is_array($context)) {
            return [];
        }

        $data = $context['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        $propagated = [];

        foreach (['request_id', 'correlation_id', 'causation_id', 'actor_public_id', 'team_public_id'] as $key) {
            $value = $this->decodedQueueContextValue($data[$key] ?? null);

            if ($value !== null) {
                $propagated[$key] = $value;
            }
        }

        return $propagated;
    }

    private function existingContextString(string $key): ?string
    {
        $value = Context::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function decodedQueueContextValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            $decoded = @unserialize($value, ['allowed_classes' => false]);

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }

            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function configureSentryScope(array $context): void
    {
        if (! class_exists(SentryIntegration::class)) {
            return;
        }

        SentryIntegration::configureScope(static function (Scope $scope) use ($context): void {
            foreach (['environment', 'release_version', 'release_id', 'source', 'module', 'event_name', 'correlation_id'] as $key) {
                if (isset($context[$key])) {
                    $scope->setTag($key, $context[$key]);
                }
            }

            $scope->setContext('atlas', $context);
        });
    }
}
