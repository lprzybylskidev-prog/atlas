<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\DTOs;

final readonly class PrivacyCoverageItem
{
    public function __construct(
        public string $key,
        public string $area,
        public string $ownerModule,
        public string $coverage,
        public string $hardDeletePolicy,
        public string $anonymizationPolicy,
        public bool $retentionControlled,
        public bool $hasParticipant,
    ) {}

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'publicId' => $this->key,
            'area' => $this->area,
            'ownerModule' => $this->ownerModule,
            'coverage' => $this->coverage,
            'hardDeletePolicy' => $this->hardDeletePolicy,
            'anonymizationPolicy' => $this->anonymizationPolicy,
            'retentionControlled' => $this->retentionControlled,
            'hasParticipant' => $this->hasParticipant,
        ];
    }
}
