<?php

declare(strict_types=1);
use App\Modules\Core\Identity\Presentation\Providers\FortifyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
