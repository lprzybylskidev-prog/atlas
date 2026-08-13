<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Providers;

use App\Modules\Core\Calendar\Application\Permissions\CalendarPermissionCatalog;
use Illuminate\Support\ServiceProvider;

final class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([CalendarPermissionCatalog::class], 'atlas.permission_catalogs');
    }
}
