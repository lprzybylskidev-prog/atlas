<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\Contracts;

use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyConflict;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyQuery;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyWindow;

interface FreeBusyLookup
{
    /** @return list<FreeBusyWindow> */
    public function windows(FreeBusyQuery $query): array;

    /** @return list<FreeBusyConflict> */
    public function conflicts(FreeBusyQuery $query): array;
}
