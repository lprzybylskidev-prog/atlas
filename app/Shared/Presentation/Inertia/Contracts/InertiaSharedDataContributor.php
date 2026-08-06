<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia\Contracts;

use Illuminate\Http\Request;

interface InertiaSharedDataContributor
{
    public function key(): string;

    /**
     * @return array<string, mixed>
     */
    public function data(Request $request): array;
}
