<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Enums;

enum ProcessLogSeverity: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
