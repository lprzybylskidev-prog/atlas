<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

use App\Modules\Core\Authorization\Application\Public\DTOs\OnboardingPackagePreview;

interface OnboardingPackageDirectory
{
    /**
     * @return list<OnboardingPackagePreview>
     */
    public function all(): array;
}
