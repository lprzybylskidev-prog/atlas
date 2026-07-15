<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Mfa;

final readonly class MfaRequirementContext
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public ?string $userPublicId = null,
        public ?string $teamPublicId = null,
        public ?string $operation = null,
        public array $permissions = [],
    ) {}
}
