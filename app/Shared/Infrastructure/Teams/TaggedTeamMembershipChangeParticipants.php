<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Teams;

use App\Shared\Application\Teams\Contracts\TeamMembershipChangeParticipant;
use Illuminate\Contracts\Container\Container;

final readonly class TaggedTeamMembershipChangeParticipants implements TeamMembershipChangeParticipant
{
    public function __construct(private Container $container) {}

    public function teamMembershipChanged(string $teamPublicId): void
    {
        foreach ($this->container->tagged('atlas.team_membership_change_participants') as $participant) {
            if ($participant instanceof TeamMembershipChangeParticipant && $participant !== $this) {
                $participant->teamMembershipChanged($teamPublicId);
            }
        }
    }
}
