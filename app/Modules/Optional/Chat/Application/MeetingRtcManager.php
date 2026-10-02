<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRtcSession;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;

final readonly class MeetingRtcManager
{
    public function __construct(private MeetingStore $meetings, private ChatTransaction $transaction, private ChatModuleAccess $access, private UserLookup $users, private RtcGateway $gateway) {}

    /** @return array{session:MeetingRtcSession,rtc:RtcParticipantAccess} */
    public function join(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, bool $camera, bool $microphone): array
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::CALL_JOIN);
        $userId = $this->userId($actor);
        [$meeting, $session] = $this->transaction->run(function () use ($meetingPublicId, $occurrenceDate, $userId, $camera, $microphone): array {
            $meeting = $this->meeting($meetingPublicId, $userId, true);
            if (! $meeting->mode->hasRtc() || $meeting->status->value === 'cancelled') {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            $session = $this->meetings->rtcSession($meeting, $occurrenceDate, true);
            if ($session === null) {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            if ($session->endedAt !== null || $session->locked) {
                throw MeetingOperationDenied::rtcUnavailable();
            }
            $room = $session->roomName === '' ? 'atlas-meeting-'.strtolower($meeting->publicId).'-'.str_replace('-', '', $occurrenceDate) : $session->roomName;
            if ($session->roomName === '') {
                $session = $this->meetings->startRtcSession($session->occurrenceId, $room);
            }
            $this->meetings->joinRtcParticipant($session->occurrenceId, $userId, $camera, $microphone);

            return [$meeting, $this->meetings->rtcSession($meeting, $occurrenceDate, true) ?? throw MeetingOperationDenied::rtcUnavailable()];
        });
        $summaries = $this->users->displaySummariesForPublicIds([$actor]);
        $displayName = array_key_exists($actor, $summaries) ? $summaries[$actor]->name : 'Atlas user';
        $admission = new RtcSessionAdmission($meeting->publicId, $session->roomName, $meeting->mode->value === 'hybrid' ? RtcSessionMode::HybridMeeting : RtcSessionMode::OnlineMeeting, $actor, $displayName);
        $this->gateway->prepareRoom($admission);

        return ['session' => $session, 'rtc' => $this->gateway->issueParticipantAccess($admission)];
    }

    public function leave(string $actor, string $team, string $meetingPublicId, string $occurrenceDate): MeetingRtcSession
    {
        return $this->selfMutation($actor, $team, $meetingPublicId, $occurrenceDate, function (MeetingRtcSession $s, int $id): void {
            $this->meetings->leaveRtcParticipant($s->occurrenceId, $id);
        });
    }

    public function media(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, bool $camera, bool $microphone): MeetingRtcSession
    {
        return $this->selfMutation($actor, $team, $meetingPublicId, $occurrenceDate, function (MeetingRtcSession $s, int $id) use ($camera, $microphone): void {
            $this->meetings->setRtcParticipantMedia($s->occurrenceId, $id, $camera, $microphone);
        });
    }

    public function screenShare(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, bool $active): MeetingRtcSession
    {
        $this->access->ensureAllowed($actor, $team, $active ? ChatPermissionCatalog::SCREEN_SHARE_STORE : ChatPermissionCatalog::SCREEN_SHARE_DESTROY);

        return $this->selfMutation($actor, $team, $meetingPublicId, $occurrenceDate, function (MeetingRtcSession $s, int $id) use ($active): void {
            $this->meetings->setRtcParticipantScreenShare($s->occurrenceId, $id, $active);
        });
    }

    public function moderate(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, string $participant, string $action): MeetingRtcSession
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::MEETING_MODERATE);
        $actorId = $this->userId($actor);
        $participantId = $this->userId($participant);
        $session = $this->transaction->run(function () use ($meetingPublicId, $occurrenceDate, $actorId, $participantId, $action): MeetingRtcSession {
            $meeting = $this->meeting($meetingPublicId, $actorId, true);
            if ($meeting->organizerUserId !== $actorId || $participantId === $actorId) {
                throw MeetingOperationDenied::organizerOnly();
            }
            $session = $this->session($meeting, $occurrenceDate, true);
            match ($action) {
                'mute' => $this->meetings->setRtcParticipantMedia($session->occurrenceId, $participantId, $this->camera($session, $participantId), false),
                'disable_microphone' => $this->meetings->setRtcParticipantMicrophoneAllowed($session->occurrenceId, $participantId, false),
                'restore_microphone' => $this->meetings->setRtcParticipantMicrophoneAllowed($session->occurrenceId, $participantId, true),
                'camera_off' => $this->meetings->setRtcParticipantMedia($session->occurrenceId, $participantId, false, $this->microphone($session, $participantId)),
                'stop_screen_share' => $this->meetings->setRtcParticipantScreenShare($session->occurrenceId, $participantId, false),
                'kick' => $this->meetings->banRtcParticipant($session->occurrenceId, $participantId),
                default => throw MeetingOperationDenied::invalidRtcAction(),
            };

            return $this->session($meeting, $occurrenceDate, true);
        });
        if ($action === 'kick' && $session->roomName !== '') {
            $this->gateway->removeParticipant($session->roomName, 'user-'.$participant);
        }

        return $session;
    }

    public function lock(string $actor, string $team, string $meetingPublicId, string $occurrenceDate, bool $locked): MeetingRtcSession
    {
        return $this->organizerMutation($actor, $team, $meetingPublicId, $occurrenceDate, function (MeetingRtcSession $s): void {
            $this->meetings->setRtcLocked($s->occurrenceId, true);
        }, $locked);
    }

    public function end(string $actor, string $team, string $meetingPublicId, string $occurrenceDate): MeetingRtcSession
    {
        return $this->organizerMutation($actor, $team, $meetingPublicId, $occurrenceDate, function (MeetingRtcSession $s): void {
            $this->meetings->endRtcSession($s->occurrenceId);
            if ($s->roomName !== '') {
                $this->gateway->endRoom($s->roomName);
            }
        });
    }

    private function organizerMutation(string $actor, string $team, string $meetingPublicId, string $date, callable $mutation, ?bool $locked = null): MeetingRtcSession
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::MEETING_MODERATE);
        $id = $this->userId($actor);

        return $this->transaction->run(function () use ($meetingPublicId, $date, $mutation, $id, $locked): MeetingRtcSession {
            $m = $this->meeting($meetingPublicId, $id, true);
            if ($m->organizerUserId !== $id) {
                throw MeetingOperationDenied::organizerOnly();
            } $s = $this->session($m, $date, true);
            if ($locked !== null) {
                $this->meetings->setRtcLocked($s->occurrenceId, $locked);
            } else {
                $mutation($s);
            }

            return $this->session($m, $date, true);
        });
    }

    private function selfMutation(string $actor, string $team, string $meetingPublicId, string $date, callable $mutation): MeetingRtcSession
    {
        $this->access->ensureAllowed($actor, $team, ChatPermissionCatalog::CALL_LEAVE);
        $id = $this->userId($actor);

        return $this->transaction->run(function () use ($meetingPublicId, $date, $mutation, $id): MeetingRtcSession {
            $m = $this->meeting($meetingPublicId, $id, true);
            $s = $this->session($m, $date, true);
            $mutation($s, $id);

            return $this->session($m, $date, true);
        });
    }

    private function meeting(string $publicId, int $userId, bool $lock): MeetingRecord
    {
        $m = $this->meetings->find($publicId, $lock) ?? throw MeetingOperationDenied::notFound();
        if ($this->meetings->invitation($m->id, $userId, $lock) === null) {
            throw MeetingOperationDenied::notInvited();
        }

        return $m;
    }

    private function session(MeetingRecord $m, string $date, bool $lock): MeetingRtcSession
    {
        return $this->meetings->rtcSession($m, $date, $lock) ?? throw MeetingOperationDenied::rtcUnavailable();
    }

    private function userId(string $publicId): int
    {
        return $this->users->internalIdForPublicId($publicId) ?? throw MeetingOperationDenied::notFound();
    }

    private function camera(MeetingRtcSession $s, int $userId): bool
    {
        foreach ($s->participants as $p) {
            if ($p['userId'] === $userId) {
                return $p['cameraEnabled'];
            }
        }

        return false;
    }

    private function microphone(MeetingRtcSession $s, int $userId): bool
    {
        foreach ($s->participants as $p) {
            if ($p['userId'] === $userId) {
                return $p['microphoneEnabled'];
            }
        }

        return false;
    }
}
