<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Enums;

enum FileScanState: string
{
    case Pending = 'pending';
    case Scanning = 'scanning';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case Unsupported = 'unsupported';

    public function blocksUse(): bool
    {
        return $this !== self::Clean;
    }
}
