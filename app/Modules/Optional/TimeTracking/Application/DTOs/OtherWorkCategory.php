<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class OtherWorkCategory
{
    public function __construct(
        public string $publicId,
        public string $scope,
        public int $scopeId,
        public string $categoryKey,
        public string $labelPl,
        public string $labelEn,
        public ?string $descriptionPl,
        public ?string $descriptionEn,
        public bool $requiresComment,
        public bool $autoApprovalEnabled,
        public bool $isActive,
    ) {}
}
