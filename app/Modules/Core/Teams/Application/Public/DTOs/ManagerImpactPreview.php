<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class ManagerImpactPreview
{
    /**
     * @param  list<string>  $affectedReportPublicIds
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $allowed,
        public string $action,
        public array $affectedReportPublicIds,
        public array $warnings = [],
    ) {}
}
