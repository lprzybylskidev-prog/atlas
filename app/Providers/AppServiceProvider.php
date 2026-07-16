<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\Contracts\ModuleGateStateProvider;
use App\Shared\Application\Modules\DefaultModuleDeactivationGuardRegistry;
use App\Shared\Application\Modules\DefaultModuleGate;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Application\Outbox\Contracts\OutboxEventRecorder;
use App\Shared\Application\Outbox\Contracts\OutboxMaintenance;
use App\Shared\Infrastructure\Modules\Activation\DatabaseModuleActivationService;
use App\Shared\Infrastructure\Modules\RegistryModuleGateStateProvider;
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
        $this->registerModuleServiceProviders();
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

    private function registerModuleServiceProviders(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($registry->startupOrder() as $module) {
            $this->app->register($module->serviceProvider());
        }
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
        $this->app->bind(ModuleGateStateProvider::class, RegistryModuleGateStateProvider::class);
        $this->app->bind(ModuleGate::class, DefaultModuleGate::class);
        $this->app->bind(ModuleActivationService::class, DatabaseModuleActivationService::class);
        $this->app->singleton(ModuleDeactivationGuardRegistry::class, fn (): DefaultModuleDeactivationGuardRegistry => new DefaultModuleDeactivationGuardRegistry(
            $this->moduleDeactivationGuards(),
        ));

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

    /**
     * @return list<ModuleDeactivationGuard>
     */
    private function moduleDeactivationGuards(): array
    {
        $guards = [];

        foreach ($this->app->tagged('atlas.module_deactivation_guards') as $guard) {
            if ($guard instanceof ModuleDeactivationGuard) {
                $guards[] = $guard;
            }
        }

        return $guards;
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
