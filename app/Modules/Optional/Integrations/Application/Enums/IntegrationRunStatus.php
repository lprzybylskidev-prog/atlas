<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Enums;

enum IntegrationRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
