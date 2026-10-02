<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptionSegment
{
    public function __construct(
        public string $text,
        public ?int $startsAtMilliseconds = null,
        public ?int $endsAtMilliseconds = null,
        public ?string $speaker = null,
    ) {}

    /** @return array{text:string,startsAtMilliseconds:?int,endsAtMilliseconds:?int,speaker:?string} */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'startsAtMilliseconds' => $this->startsAtMilliseconds,
            'endsAtMilliseconds' => $this->endsAtMilliseconds,
            'speaker' => $this->speaker,
        ];
    }
}
