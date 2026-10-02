<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Rtc;

use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcRecordingStart;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;

final class UnavailableRtcGateway implements RtcGateway
{
    public function prepareRoom(RtcSessionAdmission $admission): void
    {
        throw RtcUnavailable::disabled();
    }

    public function issueParticipantAccess(RtcSessionAdmission $admission): RtcParticipantAccess
    {
        throw RtcUnavailable::disabled();
    }

    public function removeParticipant(string $roomName, string $participantIdentity): void
    {
        throw RtcUnavailable::disabled();
    }

    public function endRoom(string $roomName): void
    {
        throw RtcUnavailable::disabled();
    }

    public function startRoomCompositeRecording(string $roomName, string $recordingPublicId, int $segment): RtcRecordingStart
    {
        throw RtcUnavailable::disabled();
    }

    public function stopRoomCompositeRecording(string $egressId): void
    {
        throw RtcUnavailable::disabled();
    }
}
