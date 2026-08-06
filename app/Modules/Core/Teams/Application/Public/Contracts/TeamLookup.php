<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

interface TeamLookup
{
    public function internalIdForPublicId(string $teamPublicId): ?int;

    public function publicIdForInternalId(int $teamId): ?string;

    /**
     * @param  list<string>  $teamPublicIds
     * @return list<int>
     */
    public function internalIdsForPublicIds(array $teamPublicIds): array;
}
