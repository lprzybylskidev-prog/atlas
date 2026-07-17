<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Presentation\Providers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Public\Contracts\AdministrativeSecuritySettings;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;
use App\Modules\Core\Settings\Application\Settings\SettingsDefaults;
use App\Modules\Core\Settings\Application\Settings\SettingValueValidator;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseAdministrativeSecuritySettings;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseSecuritySessionSettings;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseSettingsStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SettingsStore::class, function (): DatabaseSettingsStore {
            return new DatabaseSettingsStore(
                $this->app->make(ConnectionInterface::class),
                $this->app->make(CacheRepository::class),
                $this->app->make(SettingsDefaults::class),
                $this->app->make(SettingValueValidator::class),
                $this->app->make(AuditRecorder::class),
            );
        });
        $this->app->bind(AdministrativeSecuritySettings::class, DatabaseAdministrativeSecuritySettings::class);
        $this->app->bind(SecuritySessionSettings::class, DatabaseSecuritySessionSettings::class);
    }
}
