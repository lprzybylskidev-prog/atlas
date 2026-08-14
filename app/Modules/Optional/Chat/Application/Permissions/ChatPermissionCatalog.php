<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Permissions;

use App\Shared\Application\Chat\Permissions\ChatPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ChatPermissionCatalog implements ModulePermissionContribution
{
    public const INDEX = ChatPermissionNames::INDEX;

    public const DIRECT_CONVERSATION_STORE = ChatPermissionNames::DIRECT_CONVERSATION_STORE;

    public const GROUP_STORE = ChatPermissionNames::GROUP_STORE;

    public const TEAM_CONVERSATION_SHOW = ChatPermissionNames::TEAM_CONVERSATION_SHOW;

    public const MESSAGE_STORE = ChatPermissionNames::MESSAGE_STORE;

    public const CONTENT_INDEX = ChatPermissionNames::CONTENT_INDEX;

    public const ATTACHMENT_STORE = ChatPermissionNames::ATTACHMENT_STORE;

    public const ATTACHMENT_SHOW = ChatPermissionNames::ATTACHMENT_SHOW;

    public const ATTACHMENT_RETRY = ChatPermissionNames::ATTACHMENT_RETRY;

    public const ATTACHMENT_DESTROY = ChatPermissionNames::ATTACHMENT_DESTROY;

    public const ATTACHMENT_DOWNLOAD = ChatPermissionNames::ATTACHMENT_DOWNLOAD;

    public const VOICE_MESSAGE_STORE = ChatPermissionNames::VOICE_MESSAGE_STORE;

    public const REALTIME_RECONCILE = ChatPermissionNames::REALTIME_RECONCILE;

    public const REALTIME_HEARTBEAT = ChatPermissionNames::REALTIME_HEARTBEAT;

    public const REALTIME_STATUS = ChatPermissionNames::REALTIME_STATUS;

    public const REALTIME_DELIVERED = ChatPermissionNames::REALTIME_DELIVERED;

    public const REALTIME_READ = ChatPermissionNames::REALTIME_READ;

    public const REALTIME_UNREAD = ChatPermissionNames::REALTIME_UNREAD;

    public const REALTIME_UNREAD_TOTAL = ChatPermissionNames::REALTIME_UNREAD_TOTAL;

    public const CALL_START = ChatPermissionNames::CALL_START;

    public const CALL_JOIN = ChatPermissionNames::CALL_JOIN;

    public const SCREEN_SHARE_STORE = ChatPermissionNames::SCREEN_SHARE_STORE;

    public const MEETING_STORE = ChatPermissionNames::MEETING_STORE;

    public const MEETING_INVITATION_STORE = ChatPermissionNames::MEETING_INVITATION_STORE;

    public const MEETING_MODERATE = ChatPermissionNames::MEETING_MODERATE;

    public const RECORDING_MANAGE = ChatPermissionNames::RECORDING_MANAGE;

    public const RECORDING_SHOW = ChatPermissionNames::RECORDING_SHOW;

    public const RECORDING_DOWNLOAD = ChatPermissionNames::RECORDING_DOWNLOAD;

    public const RECORDING_SHARE = ChatPermissionNames::RECORDING_SHARE;

    public const TRANSCRIPTION_STORE = ChatPermissionNames::TRANSCRIPTION_STORE;

    public const TRANSCRIPTION_SHOW = ChatPermissionNames::TRANSCRIPTION_SHOW;

    public const TRANSCRIPTION_UPDATE = ChatPermissionNames::TRANSCRIPTION_UPDATE;

    public const TRANSCRIPTION_SHARE = ChatPermissionNames::TRANSCRIPTION_SHARE;

    public const ADMIN_OPERATIONS_INDEX = ChatPermissionNames::ADMIN_OPERATIONS_INDEX;

    public const ADMIN_RETENTION_UPDATE = ChatPermissionNames::ADMIN_RETENTION_UPDATE;

    public const ADMIN_RECORDING_RETENTION_UPDATE = ChatPermissionNames::ADMIN_RECORDING_RETENTION_UPDATE;

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::INDEX, 'Use internal Chat.'),
            new ModulePermissionDefinition(self::DIRECT_CONVERSATION_STORE, 'Start direct conversations.'),
            new ModulePermissionDefinition(self::GROUP_STORE, 'Create Chat groups.'),
            new ModulePermissionDefinition(self::TEAM_CONVERSATION_SHOW, 'Open the authorized active Team conversation.'),
            new ModulePermissionDefinition(self::MESSAGE_STORE, 'Send messages in authorized conversations.'),
            new ModulePermissionDefinition(self::CONTENT_INDEX, 'Browse authorized conversation media, files, and links.'),
            new ModulePermissionDefinition(self::ATTACHMENT_STORE, 'Upload Chat attachments through Files.'),
            new ModulePermissionDefinition(self::ATTACHMENT_SHOW, 'View authorized Chat attachment status.'),
            new ModulePermissionDefinition(self::ATTACHMENT_RETRY, 'Retry scanning an owned Chat attachment.'),
            new ModulePermissionDefinition(self::ATTACHMENT_DESTROY, 'Discard an owned unsent Chat attachment.'),
            new ModulePermissionDefinition(self::ATTACHMENT_DOWNLOAD, 'Download or preview an authorized clean Chat attachment.'),
            new ModulePermissionDefinition(self::VOICE_MESSAGE_STORE, 'Send Chat voice messages through Files.'),
            new ModulePermissionDefinition(self::REALTIME_RECONCILE, 'Reconcile authorized Chat state after connect or reconnect.'),
            new ModulePermissionDefinition(self::REALTIME_HEARTBEAT, 'Refresh the current Chat presence heartbeat.'),
            new ModulePermissionDefinition(self::REALTIME_STATUS, 'Set the current Chat manual status.'),
            new ModulePermissionDefinition(self::REALTIME_DELIVERED, 'Acknowledge delivery of an authorized Chat message.'),
            new ModulePermissionDefinition(self::REALTIME_READ, 'Advance the read cursor in an authorized conversation.'),
            new ModulePermissionDefinition(self::REALTIME_UNREAD, 'Mark an authorized Chat message as unread.'),
            new ModulePermissionDefinition(self::REALTIME_UNREAD_TOTAL, 'Read the current total Chat unread count.'),
            new ModulePermissionDefinition(self::CALL_START, 'Start direct, group, or Team audio/video Calls.'),
            new ModulePermissionDefinition(self::CALL_JOIN, 'Join authorized audio/video Calls and Meetings.'),
            new ModulePermissionDefinition(self::SCREEN_SHARE_STORE, 'Share the screen in an authorized Call or Meeting.'),
            new ModulePermissionDefinition(self::MEETING_STORE, 'Create immediate, scheduled, or recurring Meetings.'),
            new ModulePermissionDefinition(self::MEETING_INVITATION_STORE, 'Invite active Atlas users to owned Meetings.'),
            new ModulePermissionDefinition(self::MEETING_MODERATE, 'Moderate owned Meetings.'),
            new ModulePermissionDefinition(self::RECORDING_MANAGE, 'Manage recording for an owned Meeting.'),
            new ModulePermissionDefinition(self::RECORDING_SHOW, 'View authorized Meeting recordings.'),
            new ModulePermissionDefinition(self::RECORDING_DOWNLOAD, 'Download authorized Meeting recordings.'),
            new ModulePermissionDefinition(self::RECORDING_SHARE, 'Share authorized Meeting recordings with selected Atlas users.'),
            new ModulePermissionDefinition(self::TRANSCRIPTION_STORE, 'Request transcription of an authorized Meeting recording.'),
            new ModulePermissionDefinition(self::TRANSCRIPTION_SHOW, 'View authorized Meeting transcripts.'),
            new ModulePermissionDefinition(self::TRANSCRIPTION_UPDATE, 'Edit authorized Meeting transcripts.'),
            new ModulePermissionDefinition(self::TRANSCRIPTION_SHARE, 'Share authorized Meeting transcripts with selected Atlas users.'),
            new ModulePermissionDefinition(self::ADMIN_OPERATIONS_INDEX, 'View aggregate Chat operational status without private communication content.'),
            new ModulePermissionDefinition(self::ADMIN_RETENTION_UPDATE, 'Administer Chat retention without private communication content access.'),
            new ModulePermissionDefinition(self::ADMIN_RECORDING_RETENTION_UPDATE, 'Administer Meeting recording retention without private communication content access.'),
        ];
    }
}
