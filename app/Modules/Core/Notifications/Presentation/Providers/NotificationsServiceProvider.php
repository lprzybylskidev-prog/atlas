<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Providers;

use App\Modules\Core\Notifications\Application\NotificationTypeCatalog;
use App\Modules\Core\Notifications\Application\Permissions\NotificationPermissionCatalog;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationMaintenance;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationTypeDirectory;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimeFeed;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\UserNotificationEmailPreferences;
use App\Modules\Core\Notifications\Infrastructure\Persistence\DatabaseNotificationStore;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseNotificationStore::class);
        $this->app->bind(NotificationPublisher::class, DatabaseNotificationStore::class);
        $this->app->bind(NotificationInbox::class, DatabaseNotificationStore::class);
        $this->app->bind(NotificationMaintenance::class, DatabaseNotificationStore::class);
        $this->app->bind(RealtimePublisher::class, DatabaseNotificationStore::class);
        $this->app->bind(RealtimeFeed::class, DatabaseNotificationStore::class);
        $this->app->bind(NotificationTypeDirectory::class, NotificationTypeCatalog::class);
        $this->app->bind(NotificationEmailPreferenceManager::class, UserNotificationEmailPreferences::class);
        $this->app->tag([NotificationPermissionCatalog::class], 'atlas.permission_catalogs');
    }
}
