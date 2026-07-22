<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\DTOs;

final readonly class ReportExportDispatchResult
{
    public function __construct(
        public string $exportRequestPublicId,
        public string $executionMode,
        public ?string $processRunPublicId = null,
        public ?string $artifactPublicId = null,
    ) {}
}
