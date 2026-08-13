<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class StructuralRoleChangePreview
{
    /**
     * @param  list<string>  $endingRelationshipPublicIds
     * @param  list<string>  $affectedUserPublicIds
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $allowed,
        public string $currentRole,
        public string $targetRole,
        public array $endingRelationshipPublicIds,
        public array $affectedUserPublicIds,
        public array $warnings = [],
    ) {}
}
