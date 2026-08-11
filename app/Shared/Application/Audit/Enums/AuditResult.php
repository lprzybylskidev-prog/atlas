<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\Enums;

enum AuditResult: string
{
    case Succeeded = 'succeeded';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
