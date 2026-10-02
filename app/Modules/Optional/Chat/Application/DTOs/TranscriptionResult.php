<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class TranscriptionResult
{
    /** @param list<TranscriptionSegment> $segments */
    public function __construct(public string $text, public array $segments = []) {}
}
