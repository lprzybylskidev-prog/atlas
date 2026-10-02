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

    public const REALTIME_RECONCILE = 'chat.realtime.reconcile';

    public const REALTIME_HEARTBEAT = 'chat.realtime.heartbeat';

    public const REALTIME_STATUS = 'chat.realtime.status';

    public const REALTIME_DELIVERED = 'chat.realtime.delivered';

    public const REALTIME_READ = 'chat.realtime.read';

    public const REALTIME_UNREAD = 'chat.realtime.unread';

    public const REALTIME_UNREAD_TOTAL = 'chat.realtime.unread-total';

    public const CALL_START = 'chat.calls.start';

    public const CALL_JOIN = 'chat.calls.join';

    public const CALL_INDEX = 'chat.calls.index';

    public const CALL_CURRENT = 'chat.calls.current';

    public const CALL_DECLINE = 'chat.calls.decline';

    public const CALL_LEAVE = 'chat.calls.leave';

    public const CALL_MEDIA_UPDATE = 'chat.calls.media.update';

    public const CALL_PREFERENCES_UPDATE = 'chat.calls.preferences.update';

    public const CALL_PREFERENCES_SHOW = 'chat.calls.preferences.show';

    public const SCREEN_SHARE_STORE = 'chat.screen-shares.store';

    public const SCREEN_SHARE_DESTROY = 'chat.screen-shares.destroy';

    public const MEETING_STORE = 'chat.meetings.store';

    public const MEETING_INDEX = 'chat.meetings.index';

    public const MEETING_SHOW = 'chat.meetings.show';

    public const MEETING_UPDATE = 'chat.meetings.update';

    public const MEETING_CANCEL = 'chat.meetings.cancel';

    public const MEETING_RESPONSE_UPDATE = 'chat.meetings.response.update';

    public const MEETING_INVITATION_STORE = 'chat.meetings.invitations.store';

    public const MEETING_MODERATE = 'chat.meetings.moderate';

    public const MEETING_RTC_JOIN = 'chat.meetings.rtc.join';

    public const MEETING_RTC_LEAVE = 'chat.meetings.rtc.leave';

    public const MEETING_RTC_MEDIA_UPDATE = 'chat.meetings.rtc.media.update';

    public const MEETING_RTC_SCREEN_SHARE_STORE = 'chat.meetings.rtc.screen-shares.store';

    public const MEETING_RTC_SCREEN_SHARE_DESTROY = 'chat.meetings.rtc.screen-shares.destroy';

    public const MEETING_RTC_MODERATE = 'chat.meetings.rtc.moderate';

    public const MEETING_RTC_LOCK_UPDATE = 'chat.meetings.rtc.lock.update';

    public const MEETING_RTC_END = 'chat.meetings.rtc.end';

    public const RECORDING_MANAGE = 'chat.meetings.recordings.manage';

    public const RECORDING_STATE = 'chat.meetings.recordings.state';

    public const RECORDING_SHOW = 'chat.meetings.recordings.show';

    public const RECORDING_DOWNLOAD = 'chat.meetings.recordings.download';

    public const RECORDING_SHARE = 'chat.meetings.recordings.share';

    public const RECORDING_SHARE_REVOKE = 'chat.meetings.recordings.shares.revoke';

    public const TRANSCRIPTION_STORE = 'chat.meetings.transcriptions.store';

    public const TRANSCRIPTION_SHOW = 'chat.meetings.transcriptions.show';

    public const TRANSCRIPTION_UPDATE = 'chat.meetings.transcriptions.update';

    public const TRANSCRIPTION_SHARE = 'chat.meetings.transcriptions.share';

    public const ADMIN_OPERATIONS_INDEX = 'admin.chat.operations.index';

    public const ADMIN_RETENTION_UPDATE = 'admin.chat.retention.update';

    public const ADMIN_RECORDING_RETENTION_UPDATE = 'admin.chat.recording-retention.update';

    public const ADMIN_RECORDING_RETENTION_RUN = 'admin.chat.recording-retention.run';
}
