<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class ManagerHierarchyNode
{
    /**
     * @param  list<ManagerHierarchyNode>  $reports
     */
    public function __construct(
        public string $userPublicId,
        public string $name,
        public string $email,
        public bool $headManager,
        public array $reports,
    ) {}
}
