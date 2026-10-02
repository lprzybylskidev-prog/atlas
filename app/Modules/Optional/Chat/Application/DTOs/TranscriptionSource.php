<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptionSource
{
    public function __construct(
        public string $recordingPublicId,
        public string $filePublicId,
        public string $disk,
        public string $path,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
