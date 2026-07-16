<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Providers;

use App\Modules\Core\Audit\Application\Permissions\AuditPermissionCatalog;
use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Infrastructure\Persistence\AuditSecurityAuditRecorder;
use App\Modules\Core\Audit\Infrastructure\Persistence\DatabaseAuditRecorder;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([AuditPermissionCatalog::class], 'atlas.permission_catalogs');

        $this->app->bind(AuditRecorder::class, function (): DatabaseAuditRecorder {
            return new DatabaseAuditRecorder($this->app->make(ConnectionInterface::class));
        });

        $this->app->bind(SecurityAuditRecorder::class, AuditSecurityAuditRecorder::class);
    }
}
