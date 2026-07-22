<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class GeneratedReportArtifact
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $contents,
    ) {}
}
