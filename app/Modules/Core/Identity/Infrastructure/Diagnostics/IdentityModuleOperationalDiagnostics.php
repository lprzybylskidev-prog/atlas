<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Diagnostics;

use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use Illuminate\Support\Facades\DB;

final class IdentityModuleOperationalDiagnostics implements ModuleOperationalDiagnostics
{
    public function moduleKey(): string
    {
        return 'identity';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array
    {
        $rejections = (int) DB::table(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($rejections === 0) {
            return [];
        }

        return [[
            'severity' => 'info',
            'label' => 'Rate-limit rejections',
            'description' => 'Rate limits rejected requests during the last 24 hours.',
            'value' => $rejections,
        ]];
    }
}
