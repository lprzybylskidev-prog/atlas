<?php

declare(strict_types=1);

namespace App\Shared\Application\Mail\Contracts;

use App\Shared\Application\Mail\DTOs\MailLocaleOrder;

interface MailLocaleSelector
{
    public function select(?int $userId = null, ?int $teamId = null): MailLocaleOrder;
}
