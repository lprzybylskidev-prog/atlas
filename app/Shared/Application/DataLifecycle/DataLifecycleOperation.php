<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

enum DataLifecycleOperation: string
{
    case Delete = 'delete';
    case Anonymize = 'anonymize';
}
