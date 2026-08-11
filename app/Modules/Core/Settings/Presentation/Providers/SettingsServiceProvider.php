<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Presentation\Providers;

use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Settings\SettingsDefaults;
use App\Modules\Core\Settings\Application\Settings\SettingValueValidator;
use App\Modules\Core\Settings\Infrastructure\Mail\DatabaseMailLocaleSelector;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseAdministrativeSecuritySettings;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabasePasswordSecuritySettings;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseSecuritySessionSettings;
use App\Modules\Core\Settings\Infrastructure\Persistence\DatabaseSettingsStore;
use App\Modules\Core\Settings\Presentation\Inertia\SettingsInertiaData;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Mail\Contracts\MailLocaleSelector;
use App\Shared\Application\Security\Contracts\AdministrativeSecuritySettings;
use App\Shared\Application\Security\Contracts\PasswordSecuritySettings;
use App\Shared\Application\Security\Contracts\SecuritySessionSettings;
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
        $this->app->bind(PasswordSecuritySettings::class, DatabasePasswordSecuritySettings::class);
        $this->app->bind(SecuritySessionSettings::class, DatabaseSecuritySessionSettings::class);
        $this->app->bind(MailLocaleSelector::class, DatabaseMailLocaleSelector::class);
        $this->app->tag([SettingsInertiaData::class], 'atlas.inertia_shared_data');
    }
}
