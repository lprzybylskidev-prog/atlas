<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Transcription;

use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionProviderResponse;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionResult;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSegment;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSource;

/** Deterministic non-production adapter used only by automated tests. */
final class DeterministicTranscriptionProvider implements TranscriptionProvider
{
    /** @var array<string, int> */
    private array $polls = [];

    public function __construct(
        private readonly bool $asynchronous = false,
        private readonly int $pendingPolls = 1,
        private readonly string $text = 'Deterministic meeting transcript.',
    ) {}

    public function key(): string
    {
        return 'deterministic-test';
    }

    public function available(): bool
    {
        return true;
    }

    public function submit(TranscriptionSource $source, string $idempotencyKey): TranscriptionProviderResponse
    {
        if (! $this->asynchronous) {
            return TranscriptionProviderResponse::completed($this->result());
        }

        $externalJobId = 'test-'.hash('sha256', $idempotencyKey);
        $this->polls[$externalJobId] ??= 0;

        return TranscriptionProviderResponse::submitted($externalJobId);
    }

    public function poll(string $externalJobId): TranscriptionProviderResponse
    {
        $this->polls[$externalJobId] = ($this->polls[$externalJobId] ?? 0) + 1;

        return $this->polls[$externalJobId] <= $this->pendingPolls
            ? TranscriptionProviderResponse::processing($externalJobId)
            : TranscriptionProviderResponse::completed($this->result());
    }

    private function result(): TranscriptionResult
    {
        return new TranscriptionResult($this->text, [
            new TranscriptionSegment($this->text, 0, 1200, 'Speaker 1'),
        ]);
    }
}
