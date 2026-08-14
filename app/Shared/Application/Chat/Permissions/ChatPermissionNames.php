<?php

declare(strict_types=1);

namespace App\Shared\Application\Chat\Permissions;

final class ChatPermissionNames
{
    public const INDEX = 'chat.index';

    public const DIRECT_CONVERSATION_STORE = 'chat.direct-conversations.store';

    public const GROUP_STORE = 'chat.groups.store';

    public const TEAM_CONVERSATION_SHOW = 'chat.team-conversation.show';

    public const MESSAGE_STORE = 'chat.messages.store';

    public const CONTENT_INDEX = 'chat.content.index';

    public const ATTACHMENT_STORE = 'chat.attachments.store';

    public const ATTACHMENT_SHOW = 'chat.attachments.show';

    public const ATTACHMENT_RETRY = 'chat.attachments.retry';

    public const ATTACHMENT_DESTROY = 'chat.attachments.destroy';

    public const ATTACHMENT_DOWNLOAD = 'chat.attachments.download';

    public const VOICE_MESSAGE_STORE = 'chat.voice-messages.store';

    public const CALL_START = 'chat.calls.start';

    public const CALL_JOIN = 'chat.calls.join';

    public const SCREEN_SHARE_STORE = 'chat.screen-shares.store';

    public const MEETING_STORE = 'chat.meetings.store';

    public const MEETING_INVITATION_STORE = 'chat.meetings.invitations.store';

    public const MEETING_MODERATE = 'chat.meetings.moderate';

    public const RECORDING_MANAGE = 'chat.meetings.recordings.manage';

    public const RECORDING_SHOW = 'chat.meetings.recordings.show';

    public const RECORDING_DOWNLOAD = 'chat.meetings.recordings.download';

    public const RECORDING_SHARE = 'chat.meetings.recordings.share';

    public const TRANSCRIPTION_STORE = 'chat.meetings.transcriptions.store';

    public const TRANSCRIPTION_SHOW = 'chat.meetings.transcriptions.show';

    public const TRANSCRIPTION_UPDATE = 'chat.meetings.transcriptions.update';

    public const TRANSCRIPTION_SHARE = 'chat.meetings.transcriptions.share';

    public const ADMIN_OPERATIONS_INDEX = 'admin.chat.operations.index';

    public const ADMIN_RETENTION_UPDATE = 'admin.chat.retention.update';

    public const ADMIN_RECORDING_RETENTION_UPDATE = 'admin.chat.recording-retention.update';
}
