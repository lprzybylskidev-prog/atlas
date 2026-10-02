<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Audit;

final class ChatAuditEvents
{
    public const MEETING_CREATED = 'chat.meeting.created';

    public const MEETING_UPDATED = 'chat.meeting.updated';

    public const MEETING_CANCELLED = 'chat.meeting.cancelled';

    public const RECORDING_RETENTION_CONFIGURED = 'chat.recording_retention.configured';

    public const RECORDING_STARTED = 'chat.meeting_recording.started';

    public const RECORDING_PAUSED = 'chat.meeting_recording.paused';

    public const RECORDING_RESUMED = 'chat.meeting_recording.resumed';

    public const RECORDING_STOPPED = 'chat.meeting_recording.stopped';

    public const RECORDING_SHARED = 'chat.meeting_recording.shared';

    public const RECORDING_SHARE_REVOKED = 'chat.meeting_recording.share_revoked';

    public const RECORDING_REMOVED_BY_RETENTION = 'chat.meeting_recording.removed_by_retention';

    public const TRANSCRIPTION_REQUESTED = 'chat.meeting_transcription.requested';

    public const TRANSCRIPTION_COMPLETED = 'chat.meeting_transcription.completed';

    public const TRANSCRIPTION_FAILED = 'chat.meeting_transcription.failed';

    public const TRANSCRIPT_EDITED = 'chat.meeting_transcript.edited';

    public const TRANSCRIPT_SHARED = 'chat.meeting_transcript.shared';

    public const TRANSCRIPT_SHARE_REVOKED = 'chat.meeting_transcript.share_revoked';

    public const CALL_STARTED = 'chat.call.started';

    public const CALL_JOINED = 'chat.call.joined';

    public const CALL_DECLINED = 'chat.call.declined';

    public const CALL_LEFT = 'chat.call.left';

    public const CALL_SCREEN_SHARE_STARTED = 'chat.call.screen_share_started';

    public const CALL_SCREEN_SHARE_STOPPED = 'chat.call.screen_share_stopped';

    public const CALL_PREFERENCES_UPDATED = 'chat.call.preferences_updated';

    public const GROUP_CREATED = 'chat.group.created';

    public const GROUP_METADATA_CHANGED = 'chat.group.metadata_changed';

    public const GROUP_MEMBER_ADDED = 'chat.group.member_added';

    public const GROUP_MEMBER_REMOVED = 'chat.group.member_removed';

    public const GROUP_OWNERSHIP_TRANSFERRED = 'chat.group.ownership_transferred';

    public const GROUP_MEMBER_LEFT = 'chat.group.member_left';

    public const GROUP_CLOSED = 'chat.group.closed';
}
