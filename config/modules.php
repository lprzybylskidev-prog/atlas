<?php

declare(strict_types=1);

use App\Modules\Core\Audit\AuditModule;
use App\Modules\Core\Authorization\AuthorizationModule;
use App\Modules\Core\Identity\IdentityModule;
use App\Modules\Core\Teams\TeamsModule;
use App\Modules\Core\Users\UsersModule;

return [
    'deployed' => [
        IdentityModule::class,
        AuthorizationModule::class,
        TeamsModule::class,
        UsersModule::class,
        AuditModule::class,
    ],
];
