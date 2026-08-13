<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

enum TimelineEntryType: string
{
    case GroupCreated = 'group.created';
    case GroupMemberAdded = 'group.member_added';
    case GroupMemberRemoved = 'group.member_removed';
    case GroupMemberLeft = 'group.member_left';
    case GroupOwnershipTransferred = 'group.ownership_transferred';
    case GroupMetadataChanged = 'group.metadata_changed';
    case GroupClosed = 'group.closed';
    case TeamMembershipSynchronized = 'team.membership_synchronized';
    case MeetingScheduled = 'meeting.scheduled';
    case MeetingParticipantInvited = 'meeting.participant_invited';
    case MeetingParticipantRemoved = 'meeting.participant_removed';
    case MeetingParticipantResponseChanged = 'meeting.participant_response_changed';
}
