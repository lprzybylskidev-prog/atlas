<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Domain;

enum AccountSensitivity: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';
    case Technical = 'technical';
    case Service = 'service';
    case Integration = 'integration';

    public function impersonableByDefault(): bool
    {
        return $this === self::Normal;
    }

    public function human(): bool
    {
        return $this === self::Normal || $this === self::Sensitive;
    }
}
