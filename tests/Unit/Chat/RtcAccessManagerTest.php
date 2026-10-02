<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcRecordingStart;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Application\RtcAccessManager;
use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RtcAccessManagerTest extends TestCase
{
    public function test_authorized_online_session_prepares_room_and_issues_short_lived_access(): void
    {
        $admission = $this->admission(RtcSessionMode::OnlineMeeting);
        $gateway = new RecordingRtcGateway;
        $manager = $this->manager(new StaticRtcAuthorizer($admission), $gateway);

        $access = $manager->issueParticipantAccess('meeting-1', 'user-1', 'team-1');

        self::assertSame('atlas-meeting-meeting-1', $access->roomName);
        self::assertSame(1, $gateway->prepared);
        self::assertSame(1, $gateway->issued);
    }

    public function test_unauthorized_room_never_reaches_livekit_gateway(): void
    {
        $gateway = new RecordingRtcGateway;
        $manager = $this->manager(new DenyingRtcAuthorizer, $gateway);

        $this->expectException(RtcAccessDenied::class);

        try {
            $manager->issueParticipantAccess('guessed-room', 'user-1', 'team-1');
        } finally {
            self::assertSame(0, $gateway->prepared);
            self::assertSame(0, $gateway->issued);
        }
    }

    public function test_in_person_meeting_never_creates_room_or_token(): void
    {
        $gateway = new RecordingRtcGateway;
        $manager = $this->manager(new StaticRtcAuthorizer($this->admission(RtcSessionMode::InPersonMeeting)), $gateway);

        $this->expectException(RtcAccessDenied::class);

        try {
            $manager->issueParticipantAccess('meeting-1', 'user-1', 'team-1');
        } finally {
            self::assertSame(0, $gateway->prepared);
            self::assertSame(0, $gateway->issued);
        }
    }

    public function test_authorizer_cannot_substitute_another_participant_identity(): void
    {
        $gateway = new RecordingRtcGateway;
        $admission = new RtcSessionAdmission(
            sessionPublicId: 'meeting-1',
            roomName: 'atlas-meeting-meeting-1',
            mode: RtcSessionMode::HybridMeeting,
            userPublicId: 'user-2',
            participantName: 'Other user',
        );
        $manager = $this->manager(new StaticRtcAuthorizer($admission), $gateway);

        $this->expectException(RtcAccessDenied::class);

        try {
            $manager->issueParticipantAccess('meeting-1', 'user-1', 'team-1');
        } finally {
            self::assertSame(0, $gateway->prepared);
            self::assertSame(0, $gateway->issued);
        }
    }

    public function test_authorizer_cannot_substitute_another_session(): void
    {
        $gateway = new RecordingRtcGateway;
        $admission = new RtcSessionAdmission(
            sessionPublicId: 'meeting-2',
            roomName: 'atlas-meeting-meeting-2',
            mode: RtcSessionMode::OnlineMeeting,
            userPublicId: 'user-1',
            participantName: 'Atlas User',
        );
        $manager = $this->manager(new StaticRtcAuthorizer($admission), $gateway);

        $this->expectException(RtcAccessDenied::class);

        try {
            $manager->issueParticipantAccess('meeting-1', 'user-1', 'team-1');
        } finally {
            self::assertSame(0, $gateway->prepared);
            self::assertSame(0, $gateway->issued);
        }
    }

    private function manager(RtcSessionAccessAuthorizer $authorizer, RtcGateway $gateway): RtcAccessManager
    {
        $moduleGate = new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return true;
            }
        };

        return new RtcAccessManager(new ChatModuleAccess($moduleGate), $authorizer, $gateway);
    }

    private function admission(RtcSessionMode $mode): RtcSessionAdmission
    {
        return new RtcSessionAdmission(
            sessionPublicId: 'meeting-1',
            roomName: 'atlas-meeting-meeting-1',
            mode: $mode,
            userPublicId: 'user-1',
            participantName: 'Atlas User',
        );
    }
}

final readonly class StaticRtcAuthorizer implements RtcSessionAccessAuthorizer
{
    public function __construct(private RtcSessionAdmission $admission) {}

    public function authorize(string $sessionPublicId, string $userPublicId, string $activeTeamPublicId): RtcSessionAdmission
    {
        return $this->admission;
    }
}

final class DenyingRtcAuthorizer implements RtcSessionAccessAuthorizer
{
    public function authorize(string $sessionPublicId, string $userPublicId, string $activeTeamPublicId): RtcSessionAdmission
    {
        throw RtcAccessDenied::sessionUnavailable();
    }
}

final class RecordingRtcGateway implements RtcGateway
{
    public int $prepared = 0;

    public int $issued = 0;

    public function prepareRoom(RtcSessionAdmission $admission): void
    {
        $this->prepared++;
    }

    public function issueParticipantAccess(RtcSessionAdmission $admission): RtcParticipantAccess
    {
        $this->issued++;

        return new RtcParticipantAccess(
            serverUrl: 'ws://livekit.test',
            roomName: $admission->roomName,
            participantIdentity: 'user-'.$admission->userPublicId,
            token: 'rtc-fixture',
            expiresAt: new DateTimeImmutable('+5 minutes'),
        );
    }

    public function removeParticipant(string $roomName, string $participantIdentity): void {}

    public function endRoom(string $roomName): void {}

    public function startRoomCompositeRecording(string $roomName, string $recordingPublicId, int $segment): RtcRecordingStart
    {
        return new RtcRecordingStart('egress-'.$segment, 'recordings/'.$recordingPublicId.'/'.$segment.'.mp4');
    }

    public function stopRoomCompositeRecording(string $egressId): void {}
}
