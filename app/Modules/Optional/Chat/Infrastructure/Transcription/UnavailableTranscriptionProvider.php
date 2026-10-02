<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Transcription;

use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionProviderResponse;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSource;
use RuntimeException;

final class UnavailableTranscriptionProvider implements TranscriptionProvider
{
    public function key(): string
    {
        return 'none';
    }

    public function available(): bool
    {
        return false;
    }

    public function submit(TranscriptionSource $source, string $idempotencyKey): TranscriptionProviderResponse
    {
        throw new RuntimeException('No transcription provider is available.');
    }

    public function poll(string $externalJobId): TranscriptionProviderResponse
    {
        throw new RuntimeException('No transcription provider is available.');
    }
}
