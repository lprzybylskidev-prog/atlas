<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\DTOs;

final readonly class PrivacyPreviewResult
{
    /**
     * @param  list<array<string, mixed>>  $impacts
     * @param  list<array<string, string>>  $blockers
     */
    public function __construct(
        public string $publicId,
        public string $status,
        public string $confirmationPhrase,
        public array $impacts,
        public array $blockers,
        public int $participantCount,
        public int $estimatedRecords,
        public bool $canExecute,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'status' => $this->status,
            'confirmationPhrase' => $this->confirmationPhrase,
            'impacts' => $this->impacts,
            'blockers' => $this->blockers,
            'participantCount' => $this->participantCount,
            'estimatedRecords' => $this->estimatedRecords,
            'canExecute' => $this->canExecute,
        ];
    }
}
