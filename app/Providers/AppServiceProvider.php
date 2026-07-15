<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->validateCriticalConfiguration();
        $this->registerLocalDevelopmentProviders();
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
