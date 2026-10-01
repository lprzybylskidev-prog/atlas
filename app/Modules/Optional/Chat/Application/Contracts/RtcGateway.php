<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;

interface RtcGateway
{
    public function prepareRoom(RtcSessionAdmission $admission): void;

    public function issueParticipantAccess(RtcSessionAdmission $admission): RtcParticipantAccess;

    public function removeParticipant(string $roomName, string $participantIdentity): void;

    public function endRoom(string $roomName): void;
}
