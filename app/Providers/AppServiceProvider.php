<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Application\Outbox\Contracts\OutboxEventRecorder;
use App\Shared\Application\Outbox\Contracts\OutboxMaintenance;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxConsumerDeduplicator;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxEventRecorder;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxMaintenance;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->validateCriticalConfiguration();
        $this->registerModuleRegistry();
        $this->registerSharedInfrastructure();
        $this->registerLocalDevelopmentProviders();
    }

    private function registerModuleRegistry(): void
    {
        $this->app->singleton(ModuleRegistry::class, function (): ModuleRegistry {
            return new ModuleRegistry($this->deployedModuleDefinitions());
        });

        $this->app->make(ModuleRegistry::class);
    }

    /**
     * @return list<ModuleDefinition>
     */
    private function deployedModuleDefinitions(): array
    {
        $deployed = config('modules.deployed');

        if (! is_array($deployed)) {
            throw new RuntimeException('Configured deployed modules must be an array of ModuleDefinition class names.');
        }

        $modules = [];

        foreach ($deployed as $moduleClass) {
            if (! is_string($moduleClass) || ! is_subclass_of($moduleClass, ModuleDefinition::class)) {
                throw new RuntimeException('Every deployed module entry must be a ModuleDefinition class name.');
            }

            $module = $this->app->make($moduleClass);

            if (! $module instanceof ModuleDefinition) {
                throw new RuntimeException('Every deployed module entry must resolve to a ModuleDefinition instance.');
            }

            $modules[] = $module;
        }

        return $modules;
    }

    private function registerSharedInfrastructure(): void
    {
        $this->app->bind(OutboxEventRecorder::class, function (): DatabaseOutboxEventRecorder {
            return new DatabaseOutboxEventRecorder($this->app->make(ConnectionInterface::class));
        });

        $this->app->bind(OutboxMaintenance::class, function (): DatabaseOutboxMaintenance {
            return new DatabaseOutboxMaintenance($this->app->make(ConnectionInterface::class));
        });

        $this->app->bind(OutboxConsumerDeduplicator::class, function (): DatabaseOutboxConsumerDeduplicator {
            return new DatabaseOutboxConsumerDeduplicator($this->app->make(ConnectionInterface::class));
        });
    }

    private function registerLocalDevelopmentProviders(): void
    {
        if (! $this->app->environment(['local', 'development'])) {
            return;
        }

        if (! class_exists(TelescopeApplicationServiceProvider::class)) {
            return;
        }

        $this->app->register(TelescopeServiceProvider::class);
    }

    private function validateCriticalConfiguration(): void
    {
        $requiredStrings = [
            'app.name',
            'app.env',
            'app.url',
            'app.timezone',
            'app.locale',
            'app.fallback_locale',
            'database.default',
            'cache.default',
            'queue.default',
            'session.driver',
            'atlas.currency.default',
            'atlas.release.version',
            'atlas.release.id',
        ];

        foreach ($requiredStrings as $key) {
            $value = Config::get($key);

            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException(sprintf('Critical configuration [%s] must be a non-empty string.', $key));
            }
        }

        if (Config::string('app.timezone') !== 'Europe/Warsaw') {
            throw new RuntimeException('APP_TIMEZONE must be Europe/Warsaw during the Atlas foundation phase.');
        }

        if (Config::string('database.default') !== 'pgsql') {
            throw new RuntimeException('Atlas supports PostgreSQL as the only relational database.');
        }
    }
}
