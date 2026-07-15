<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Contracts;

interface FirstPasswordLinkIssuer
{
    public function issue(string $email): void;
}
