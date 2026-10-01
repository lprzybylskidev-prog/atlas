<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class CallPreferences
{
    public function __construct(
        public ?string $cameraDeviceId = null,
        public ?string $microphoneDeviceId = null,
        public ?string $speakerDeviceId = null,
        public bool $outgoingCameraEnabled = false,
    ) {}
}
