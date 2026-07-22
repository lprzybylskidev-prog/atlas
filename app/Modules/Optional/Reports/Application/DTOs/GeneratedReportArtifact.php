<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

final readonly class GeneratedReportArtifact
{
    public function __construct(
        public string $filename,
        public string $contentType,
        public string $contents,
    ) {}
}
