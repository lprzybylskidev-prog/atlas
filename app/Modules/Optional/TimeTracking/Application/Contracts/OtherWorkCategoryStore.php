<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\OtherWorkCategory;

interface OtherWorkCategoryStore
{
    /**
     * @return list<OtherWorkCategory>
     */
    public function activeForTeam(int $teamId): array;

    public function upsertTeam(
        int $teamId,
        string $categoryKey,
        string $labelPl,
        string $labelEn,
        ?string $descriptionPl,
        ?string $descriptionEn,
        bool $requiresComment,
        bool $autoApprovalEnabled = false,
    ): void;

    public function deactivateTeam(int $teamId, string $categoryKey): void;
}
