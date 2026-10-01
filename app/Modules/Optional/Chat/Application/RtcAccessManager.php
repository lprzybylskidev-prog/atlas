<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;

final readonly class RtcAccessManager
{
    public function __construct(
        private ChatModuleAccess $moduleAccess,
        private RtcSessionAccessAuthorizer $authorizer,
        private RtcGateway $gateway,
    ) {}

    public function issueParticipantAccess(
        string $sessionPublicId,
        string $userPublicId,
        string $activeTeamPublicId,
    ): RtcParticipantAccess {
        $this->moduleAccess->ensureAllowed($userPublicId, $activeTeamPublicId, ChatPermissionCatalog::CALL_JOIN);

        $admission = $this->authorizer->authorize($sessionPublicId, $userPublicId, $activeTeamPublicId);

        if ($admission->sessionPublicId !== $sessionPublicId) {
            throw RtcAccessDenied::sessionMismatch();
        }

        if ($admission->userPublicId !== $userPublicId) {
            throw RtcAccessDenied::participantMismatch();
        }

        if (! $admission->mode->supportsRtc()) {
            throw RtcAccessDenied::inPersonMeeting();
        }

        $this->gateway->prepareRoom($admission);

        return $this->gateway->issueParticipantAccess($admission);
    }
}
