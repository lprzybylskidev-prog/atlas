<?php

declare(strict_types=1);

use App\Modules\Core\Audit\AuditModule;
use App\Modules\Core\Authorization\AuthorizationModule;
use App\Modules\Core\Exports\ExportsModule;
use App\Modules\Core\Files\FilesModule;
use App\Modules\Core\Health\HealthModule;
use App\Modules\Core\Identity\IdentityModule;
use App\Modules\Core\Notifications\NotificationsModule;
use App\Modules\Core\Privacy\PrivacyModule;
use App\Modules\Core\Settings\SettingsModule;
use App\Modules\Core\Teams\TeamsModule;
use App\Modules\Core\Users\UsersModule;
use App\Modules\Optional\FeatureFlags\FeatureFlagsModule;
use App\Modules\Optional\Imports\ImportsModule;
use App\Modules\Optional\Integrations\IntegrationsModule;
use App\Modules\Optional\ManagedProcesses\ManagedProcessesModule;
use App\Modules\Optional\Reports\ReportsModule;
use App\Modules\Optional\Search\SearchModule;
use App\Modules\Optional\TimeTracking\TimeTrackingModule;

return [
    'deployed' => [
        IdentityModule::class,
        AuthorizationModule::class,
        TeamsModule::class,
        UsersModule::class,
        AuditModule::class,
        SettingsModule::class,
        NotificationsModule::class,
        HealthModule::class,
        FilesModule::class,
        FeatureFlagsModule::class,
        IntegrationsModule::class,
        ManagedProcessesModule::class,
        ExportsModule::class,
        PrivacyModule::class,
        ImportsModule::class,
        SearchModule::class,
        ReportsModule::class,
        TimeTrackingModule::class,
    ],
];
