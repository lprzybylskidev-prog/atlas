<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class RtcRecordingStart
{
    public function __construct(
        public string $egressId,
        public string $stagingPath,
    ) {}
}
