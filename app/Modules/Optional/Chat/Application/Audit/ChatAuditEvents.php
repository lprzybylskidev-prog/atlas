<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Audit;

final class ChatAuditEvents
{
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
