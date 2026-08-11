<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\VerifiedUserFixture;

interface VerifiedUserFixtureBuilder
{
    public function provide(
        string $name,
        string $email,
        string $plainPassword,
        string $accountSensitivity = 'normal',
    ): VerifiedUserFixture;
}
