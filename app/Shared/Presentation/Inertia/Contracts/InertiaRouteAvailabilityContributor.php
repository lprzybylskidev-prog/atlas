<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia\Contracts;

use Illuminate\Http\Request;

interface InertiaRouteAvailabilityContributor
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function adminRoutes(Request $request): array;

    /**
     * @return list<string>
     */
    public function applicationRoutes(Request $request): array;
}
