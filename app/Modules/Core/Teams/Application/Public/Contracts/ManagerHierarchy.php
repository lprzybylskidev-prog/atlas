<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerScope;
use App\Modules\Core\Teams\Application\Public\DTOs\StructuralRoleChangePreview;

interface ManagerHierarchy
{
    public function version(string $teamPublicId): string;

    /**
     * @return list<ManagerRelationshipSummary>
     */
    public function activeRelationships(?string $teamPublicId = null): array;

    /**
     * @return list<ManagerRelationshipSummary>
     */
    public function relationshipHistory(string $teamPublicId): array;

    /**
     * @return list<ManagerHierarchyNode>
     */
    public function tree(string $teamPublicId): array;

    public function previewAssign(string $teamPublicId, string $managerUserPublicId, string $reportUserPublicId): ManagerImpactPreview;

    public function previewEnd(string $relationshipPublicId): ManagerImpactPreview;

    public function assign(
        string $actorUserPublicId,
        string $teamPublicId,
        string $managerUserPublicId,
        string $reportUserPublicId,
        string $validFrom,
        string $reason,
        ?string $expectedVersion = null,
    ): void;

    public function end(string $actorUserPublicId, string $relationshipPublicId, string $validTo, string $reason, ?string $expectedVersion = null): void;

    public function previewStructuralRoleChange(string $teamPublicId, string $userPublicId, string $targetRole): StructuralRoleChangePreview;

    public function changeStructuralRole(
        string $actorUserPublicId,
        string $teamPublicId,
        string $userPublicId,
        string $targetRole,
        string $reason,
        ?string $expectedVersion = null,
    ): void;

    public function scopeFor(string $teamPublicId, string $managerUserPublicId): ManagerScope;
}
