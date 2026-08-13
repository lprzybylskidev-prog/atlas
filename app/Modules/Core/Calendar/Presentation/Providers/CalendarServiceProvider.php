<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Providers;

use App\Modules\Core\Calendar\Application\Contracts\CalendarContributionStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarEventStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarFixtureBuilder;
use App\Modules\Core\Calendar\Application\Contracts\CalendarPreferenceStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarTransaction;
use App\Modules\Core\Calendar\Application\Permissions\CalendarPermissionCatalog;
use App\Modules\Core\Calendar\Application\Public\Contracts\CalendarEventPublisher;
use App\Modules\Core\Calendar\Application\Public\Contracts\FreeBusyLookup;
use App\Modules\Core\Calendar\Application\Services\CalendarFreeBusyService;
use App\Modules\Core\Calendar\Infrastructure\Fixtures\DatabaseCalendarFixtureBuilder;
use App\Modules\Core\Calendar\Infrastructure\Persistence\DatabaseCalendarContributionStore;
use App\Modules\Core\Calendar\Infrastructure\Persistence\DatabaseCalendarEventStore;
use App\Modules\Core\Calendar\Infrastructure\Persistence\DatabaseCalendarPreferenceStore;
use App\Modules\Core\Calendar\Infrastructure\Persistence\DatabaseCalendarTransaction;
use App\Modules\Core\Calendar\Presentation\Inertia\CalendarRouteAvailability;
use Illuminate\Support\ServiceProvider;

final class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CalendarEventStore::class, DatabaseCalendarEventStore::class);
        $this->app->bind(CalendarPreferenceStore::class, DatabaseCalendarPreferenceStore::class);
        $this->app->bind(CalendarTransaction::class, DatabaseCalendarTransaction::class);

        if (! $this->app->isProduction()) {
            $this->app->bind(CalendarFixtureBuilder::class, DatabaseCalendarFixtureBuilder::class);
        }
        $this->app->singleton(DatabaseCalendarContributionStore::class);
        $this->app->bind(CalendarContributionStore::class, DatabaseCalendarContributionStore::class);
        $this->app->bind(CalendarEventPublisher::class, DatabaseCalendarContributionStore::class);
        $this->app->bind(FreeBusyLookup::class, CalendarFreeBusyService::class);
        $this->app->tag([CalendarPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([CalendarRouteAvailability::class], 'atlas.inertia_route_availability');
    }
}
