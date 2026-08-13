<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\Contracts;

interface TeamMembershipChangeParticipant
{
    public function teamMembershipChanged(string $teamPublicId): void;
}
