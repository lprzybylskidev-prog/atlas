<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Domain\Enums;

enum TeamStructuralRole: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case HeadManager = 'head_manager';

    public function canManageDirectReports(): bool
    {
        return $this === self::Manager;
    }

    public function canParticipateAsReport(): bool
    {
        return $this !== self::HeadManager;
    }
}
