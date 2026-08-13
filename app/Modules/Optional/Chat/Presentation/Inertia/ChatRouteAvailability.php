<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Inertia;

use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use Illuminate\Http\Request;

final readonly class ChatRouteAvailability implements InertiaRouteAvailabilityContributor
{
    public function __construct(private ChatModuleAccess $access) {}

    public function key(): string
    {
        return 'optional.chat.routes';
    }

    public function adminRoutes(Request $request): array
    {
        return $this->chatAvailable($request) ? [
            ChatPermissionCatalog::ADMIN_OPERATIONS_INDEX,
            ChatPermissionCatalog::ADMIN_RETENTION_UPDATE,
            ChatPermissionCatalog::ADMIN_RECORDING_RETENTION_UPDATE,
        ] : [];
    }

    public function applicationRoutes(Request $request): array
    {
        return $this->chatAvailable($request) ? [
            ChatPermissionCatalog::INDEX,
            ChatPermissionCatalog::DIRECT_CONVERSATION_STORE,
            ChatPermissionCatalog::GROUP_STORE,
            ChatPermissionCatalog::ATTACHMENT_STORE,
            ChatPermissionCatalog::VOICE_MESSAGE_STORE,
            ChatPermissionCatalog::CALL_START,
            ChatPermissionCatalog::CALL_JOIN,
            ChatPermissionCatalog::SCREEN_SHARE_STORE,
            ChatPermissionCatalog::MEETING_STORE,
            ChatPermissionCatalog::MEETING_INVITATION_STORE,
            ChatPermissionCatalog::MEETING_MODERATE,
            ChatPermissionCatalog::RECORDING_MANAGE,
            ChatPermissionCatalog::RECORDING_SHOW,
            ChatPermissionCatalog::RECORDING_DOWNLOAD,
            ChatPermissionCatalog::RECORDING_SHARE,
            ChatPermissionCatalog::TRANSCRIPTION_STORE,
            ChatPermissionCatalog::TRANSCRIPTION_SHOW,
            ChatPermissionCatalog::TRANSCRIPTION_UPDATE,
            ChatPermissionCatalog::TRANSCRIPTION_SHARE,
        ] : [];
    }

    private function chatAvailable(Request $request): bool
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($userPublicId)
            && is_string($teamPublicId)
            && $this->access->allows($userPublicId, $teamPublicId, ChatPermissionCatalog::INDEX);
    }
}
