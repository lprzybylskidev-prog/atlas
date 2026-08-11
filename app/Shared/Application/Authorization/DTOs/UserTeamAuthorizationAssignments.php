<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\DTOs;

final readonly class UserTeamAuthorizationAssignments
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     * @param  array<string, mixed>|null  $presetSnapshot
     * @param  array<string, int|null>  $resultingLimits
     */
    public function __construct(
        public string $userPublicId,
        public string $teamPublicId,
        public array $roleNames,
        public array $directPermissionNames,
        public ?string $provenancePublicId = null,
        public string $sourceType = 'manual',
        public ?string $sourcePublicId = null,
        public ?string $sourceDisplayNameSnapshot = null,
        public ?string $copiedFromUserPublicId = null,
        public ?int $presetVersion = null,
        public ?array $presetSnapshot = null,
        public ?string $appliedAt = null,
        public ?string $reason = null,
        public array $resultingLimits = [],
        public ?string $divergedAt = null,
        public int $version = 0,
    ) {}
}
