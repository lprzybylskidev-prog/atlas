<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Public\Contracts;

use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;

interface SearchLifecycleProjector
{
    public function supports(DataLifecycleSubject $subject, DataLifecycleOperation $operation): bool;

    /**
     * @return array<string, list<string>>
     */
    public function documentIdsFor(DataLifecycleSubject $subject, DataLifecycleOperation $operation): array;
}
