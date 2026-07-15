<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Providers;

use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\Contracts\UserAccountRepository;
use App\Modules\Core\Users\Infrastructure\Notifications\LaravelFirstPasswordLinkIssuer;
use App\Modules\Core\Users\Infrastructure\Persistence\IdentityUserAccountRepository;
use Illuminate\Support\ServiceProvider;

final class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserAccountRepository::class, IdentityUserAccountRepository::class);
        $this->app->bind(FirstPasswordLinkIssuer::class, LaravelFirstPasswordLinkIssuer::class);
    }
}
