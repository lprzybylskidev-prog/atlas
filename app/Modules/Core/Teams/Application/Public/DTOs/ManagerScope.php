<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class ManagerScope
{
    /**
     * @param  list<string>  $visibleUserPublicIds
     */
    public function __construct(
        public string $teamPublicId,
        public string $managerUserPublicId,
        public bool $headManager,
        public array $visibleUserPublicIds,
    ) {}
}
