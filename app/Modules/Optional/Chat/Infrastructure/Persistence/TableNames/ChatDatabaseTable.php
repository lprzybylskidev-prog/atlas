<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class ChatDatabaseTable
{
    public const CONVERSATIONS = DatabaseSchema::OPTIONAL_CHAT.'.conversations';

    public const CONVERSATION_MEMBERSHIPS = DatabaseSchema::OPTIONAL_CHAT.'.conversation_memberships';

    public const DIRECT_CONVERSATION_PAIRS = DatabaseSchema::OPTIONAL_CHAT.'.direct_conversation_pairs';

    public const CONVERSATION_TIMELINE_ENTRIES = DatabaseSchema::OPTIONAL_CHAT.'.conversation_timeline_entries';

    public const MESSAGES = DatabaseSchema::OPTIONAL_CHAT.'.messages';

    public const CALLS = DatabaseSchema::OPTIONAL_CHAT.'.calls';

    public const MEETINGS = DatabaseSchema::OPTIONAL_CHAT.'.meetings';

    public const MEETING_INVITATIONS = DatabaseSchema::OPTIONAL_CHAT.'.meeting_invitations';

    public const MEETING_RECORDINGS = DatabaseSchema::OPTIONAL_CHAT.'.meeting_recordings';

    public const MEETING_TRANSCRIPTIONS = DatabaseSchema::OPTIONAL_CHAT.'.meeting_transcriptions';
}
