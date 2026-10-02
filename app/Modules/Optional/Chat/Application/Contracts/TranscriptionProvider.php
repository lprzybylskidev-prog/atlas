<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\TranscriptionProviderResponse;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSource;

interface TranscriptionProvider
{
    public function key(): string;

    public function available(): bool;

    public function submit(TranscriptionSource $source, string $idempotencyKey): TranscriptionProviderResponse;

    public function poll(string $externalJobId): TranscriptionProviderResponse;
}
