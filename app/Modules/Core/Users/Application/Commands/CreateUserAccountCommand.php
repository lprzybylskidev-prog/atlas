<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Commands;

final readonly class CreateUserAccountCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $onboardingPackageName = null,
        public ?string $teamPublicId = null,
        public ?string $actorPublicId = null,
        public ?string $copyAuthorizationFromUserPublicId = null,
        public string $accountSensitivity = 'normal',
    ) {}
}
