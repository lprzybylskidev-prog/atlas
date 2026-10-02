<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptionProviderResponse
{
    private function __construct(
        public bool $complete,
        public ?string $externalJobId,
        public ?TranscriptionResult $result,
    ) {}

    public static function completed(TranscriptionResult $result): self
    {
        return new self(true, null, $result);
    }

    public static function submitted(string $externalJobId): self
    {
        return new self(false, $externalJobId, null);
    }

    public static function processing(string $externalJobId): self
    {
        return new self(false, $externalJobId, null);
    }
}
