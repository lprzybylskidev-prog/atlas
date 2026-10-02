<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::OPTIONAL_CHAT);

        Schema::create(ChatDatabaseTable::CONVERSATIONS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type', 16);
            $table->string('name', 200)->nullable();
            $table->string('avatar_file_public_id', 26)->nullable();
            $table->string('team_public_id', 26)->nullable();
            $table->string('meeting_owner_key', 120)->nullable();
            $table->boolean('system_owned')->default(false);
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['type', 'closed_at']);
            $table->index('team_public_id');
            $table->index('meeting_owner_key');
        });

        DB::statement(sprintf(
            "alter table %s add constraint chat_conversations_type_check check (type in ('direct', 'group', 'team', 'meeting'))",
            ChatDatabaseTable::CONVERSATIONS,
        ));
        DB::statement(sprintf(
            "alter table %s add constraint chat_conversations_owner_check check ((type = 'direct' and system_owned = false and name is null and team_public_id is null and meeting_owner_key is null) or (type = 'group' and system_owned = false and name is not null and team_public_id is null and meeting_owner_key is null) or (type = 'team' and system_owned = true and team_public_id is not null and meeting_owner_key is null) or (type = 'meeting' and system_owned = true and team_public_id is null and meeting_owner_key is not null))",
            ChatDatabaseTable::CONVERSATIONS,
        ));
        DB::statement(sprintf(
            'create unique index chat_conversations_team_unique on %s (team_public_id) where type = \'team\'',
            ChatDatabaseTable::CONVERSATIONS,
        ));
        DB::statement(sprintf(
            'create unique index chat_conversations_meeting_owner_unique on %s (meeting_owner_key) where type = \'meeting\'',
            ChatDatabaseTable::CONVERSATIONS,
        ));

        Schema::create(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lower_user_id');
            $table->unsignedBigInteger('higher_user_id');
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->timestampsTz();

            $table->foreign('lower_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('higher_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->unique(['lower_user_id', 'higher_user_id']);
        });
        DB::statement(sprintf(
            'alter table %s add constraint chat_direct_pair_order_check check (lower_user_id < higher_user_id)',
            ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS,
        ));

        Schema::create(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16);
            $table->string('source', 16);
            $table->string('meeting_response', 16)->nullable();
            $table->timestampTz('joined_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('ended_reason', 32)->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index(['conversation_id', 'user_id']);
            $table->index(['user_id', 'ended_at']);
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_memberships_role_check check (role in ('owner', 'member'))",
            ChatDatabaseTable::CONVERSATION_MEMBERSHIPS,
        ));
        DB::statement(sprintf(
            "alter table %s add constraint chat_memberships_source_check check (source in ('direct', 'group', 'team', 'meeting'))",
            ChatDatabaseTable::CONVERSATION_MEMBERSHIPS,
        ));
        DB::statement(sprintf(
            "alter table %s add constraint chat_memberships_response_check check (meeting_response is null or meeting_response in ('pending', 'accepted', 'declined'))",
            ChatDatabaseTable::CONVERSATION_MEMBERSHIPS,
        ));
        DB::statement(sprintf(
            'create unique index chat_memberships_active_user_unique on %s (conversation_id, user_id) where ended_at is null',
            ChatDatabaseTable::CONVERSATION_MEMBERSHIPS,
        ));
        DB::statement(sprintf(
            "create unique index chat_memberships_active_owner_unique on %s (conversation_id) where ended_at is null and role = 'owner'",
            ChatDatabaseTable::CONVERSATION_MEMBERSHIPS,
        ));

        Schema::create(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->string('type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->foreign('subject_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->nullOnDelete();
            $table->index(['conversation_id', 'occurred_at', 'id']);
        });

        DB::statement(<<<'SQL'
create or replace function optional_chat.prevent_timeline_update()
returns trigger as $$
begin
    raise exception 'Conversation timeline entries are immutable';
end;
$$ language plpgsql
SQL);
        DB::statement('create trigger conversation_timeline_entries_immutable before update on '.ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES.' for each row execute function optional_chat.prevent_timeline_update()');

        Schema::create(ChatDatabaseTable::MESSAGES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('author_user_id');
            $table->text('body');
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->unsignedBigInteger('forwarded_from_message_id')->nullable();
            $table->string('client_message_key', 120);
            $table->string('request_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('author_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('reply_to_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('forwarded_from_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->unique(['author_user_id', 'client_message_key'], 'chat_messages_author_client_key_unique');
            $table->index(['conversation_id', 'created_at', 'id']);
            $table->index('reply_to_message_id');
        });
        DB::statement(sprintf(
            'alter table %s add constraint chat_messages_version_check check (version > 0)',
            ChatDatabaseTable::MESSAGES,
        ));

        Schema::create(ChatDatabaseTable::MESSAGE_EDIT_HISTORY, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedInteger('version');
            $table->text('body');
            $table->unsignedBigInteger('edited_by_user_id');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('edited_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['message_id', 'version']);
        });

        Schema::create(ChatDatabaseTable::MESSAGE_DELETIONS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestampTz('deleted_at');

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create(ChatDatabaseTable::MESSAGE_REACTIONS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index(['message_id', 'created_at']);
        });

        Schema::create(ChatDatabaseTable::MESSAGE_MENTIONS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('type', 16);
            $table->unsignedBigInteger('mentioned_user_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('mentioned_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['message_id', 'type', 'mentioned_user_id'], 'chat_message_mentions_unique');
            $table->index(['mentioned_user_id', 'created_at']);
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_message_mentions_type_check check ((type = 'user' and mentioned_user_id is not null) or (type in ('everyone', 'online') and mentioned_user_id is null))",
            ChatDatabaseTable::MESSAGE_MENTIONS,
        ));
        DB::statement(sprintf(
            "create unique index chat_message_mentions_group_unique on %s (message_id, type) where type in ('everyone', 'online')",
            ChatDatabaseTable::MESSAGE_MENTIONS,
        ));

        Schema::create(ChatDatabaseTable::MESSAGE_PINS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedBigInteger('pinned_by_user_id');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('pinned_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
        });

        Schema::create(ChatDatabaseTable::MESSAGE_BOOKMARKS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create(ChatDatabaseTable::MESSAGE_DRAFTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('reply_to_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create(ChatDatabaseTable::MESSAGE_ATTACHMENTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('uploader_user_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('file_public_id', 26)->unique();
            $table->string('kind', 16);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestampTz('attached_at')->nullable();
            $table->timestampTz('discarded_at')->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('uploader_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->index(['conversation_id', 'message_id']);
            $table->index(['uploader_user_id', 'discarded_at']);
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_message_attachments_kind_check check (kind in ('file', 'voice'))",
            ChatDatabaseTable::MESSAGE_ATTACHMENTS,
        ));
        DB::statement(sprintf(
            "alter table %s add constraint chat_message_attachments_voice_check check ((kind = 'voice' and duration_seconds between 1 and 900 and mime_type like 'audio/%%') or (kind = 'file' and duration_seconds is null))",
            ChatDatabaseTable::MESSAGE_ATTACHMENTS,
        ));

        DB::statement(<<<'SQL'
create or replace function optional_chat.prevent_message_history_update()
returns trigger as $$
begin
    raise exception 'Message history and deletion records are immutable';
end;
$$ language plpgsql
SQL);
        DB::statement('create trigger message_edit_history_immutable before update on '.ChatDatabaseTable::MESSAGE_EDIT_HISTORY.' for each row execute function optional_chat.prevent_message_history_update()');
        DB::statement('create trigger message_deletions_immutable before update on '.ChatDatabaseTable::MESSAGE_DELETIONS.' for each row execute function optional_chat.prevent_message_history_update()');

        Schema::create(ChatDatabaseTable::CONVERSATION_REALTIME_STATES, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('last_delivered_message_id')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->unsignedBigInteger('unread_from_message_id')->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('last_delivered_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('last_read_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->foreign('unread_from_message_id')->references('id')->on(ChatDatabaseTable::MESSAGES)->restrictOnDelete();
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create(ChatDatabaseTable::USER_PRESENCE, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('manual_status', 24)->default('available');
            $table->string('custom_text', 120)->nullable();
            $table->string('custom_emoji', 32)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index('last_heartbeat_at');
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_user_presence_status_check check (manual_status in ('available', 'busy', 'do_not_disturb', 'out_of_office'))",
            ChatDatabaseTable::USER_PRESENCE,
        ));

        Schema::create(ChatDatabaseTable::CALLS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('started_by_user_id');
            $table->string('client_request_key', 120);
            $table->string('request_hash', 64);
            $table->string('room_name', 128)->unique();
            $table->boolean('initial_camera_enabled')->default(false);
            $table->string('status', 16);
            $table->timestampTz('started_at');
            $table->timestampTz('answered_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->foreign('started_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['started_by_user_id', 'client_request_key'], 'chat_calls_starter_request_unique');
            $table->index(['started_by_user_id', 'started_at']);
            $table->index(['conversation_id', 'started_at']);
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_calls_status_check check (status in ('ringing', 'active', 'ended', 'declined', 'busy', 'missed', 'failed'))",
            ChatDatabaseTable::CALLS,
        ));
        DB::statement(sprintf(
            "create unique index chat_calls_active_conversation_unique on %s (conversation_id) where status in ('ringing', 'active')",
            ChatDatabaseTable::CALLS,
        ));

        Schema::create(ChatDatabaseTable::CALL_PARTICIPANTS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('call_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16);
            $table->string('state', 16);
            $table->boolean('camera_enabled')->default(false);
            $table->boolean('microphone_enabled')->default(false);
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestampTz('screen_share_started_at')->nullable();
            $table->timestampsTz();

            $table->foreign('call_id')->references('id')->on(ChatDatabaseTable::CALLS)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['call_id', 'user_id']);
            $table->index(['user_id', 'state']);
            $table->index(['call_id', 'state']);
        });
        DB::statement(sprintf(
            "alter table %s add constraint chat_call_participants_role_check check (role in ('starter', 'invitee', 'joiner'))",
            ChatDatabaseTable::CALL_PARTICIPANTS,
        ));
        DB::statement(sprintf(
            "alter table %s add constraint chat_call_participants_state_check check (state in ('ringing', 'notified', 'joined', 'declined', 'busy', 'missed', 'left', 'failed'))",
            ChatDatabaseTable::CALL_PARTICIPANTS,
        ));
        DB::statement(sprintf(
            "create unique index chat_call_participants_active_user_unique on %s (user_id) where state = 'joined'",
            ChatDatabaseTable::CALL_PARTICIPANTS,
        ));
        DB::statement(sprintf(
            'create unique index chat_call_participants_screen_share_unique on %s (call_id) where screen_share_started_at is not null',
            ChatDatabaseTable::CALL_PARTICIPANTS,
        ));

        Schema::create(ChatDatabaseTable::CALL_PREFERENCES, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('camera_device_id', 512)->nullable();
            $table->string('microphone_device_id', 512)->nullable();
            $table->string('speaker_device_id', 512)->nullable();
            $table->boolean('outgoing_camera_enabled')->default(false);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
        });

        Schema::create(ChatDatabaseTable::MEETINGS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->ulid('series_public_id')->index();
            $table->unsignedBigInteger('organizer_user_id');
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('mode', 16);
            $table->string('location', 300)->nullable();
            $table->string('recurrence_frequency', 16)->nullable();
            $table->jsonb('recurrence_weekdays')->nullable();
            $table->date('recurrence_ends_on')->nullable();
            $table->unsignedSmallInteger('recurrence_count')->nullable();
            $table->jsonb('reminder_minutes')->default('[15]');
            $table->string('status', 16)->default('scheduled');
            $table->timestampTz('cancelled_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('organizer_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('conversation_id')->references('id')->on(ChatDatabaseTable::CONVERSATIONS)->restrictOnDelete();
            $table->index(['starts_at', 'ends_at']);
        });
        DB::statement(sprintf("alter table %s add constraint chat_meetings_mode_check check (mode in ('online', 'in_person', 'hybrid'))", ChatDatabaseTable::MEETINGS));
        DB::statement(sprintf("alter table %s add constraint chat_meetings_location_check check ((mode = 'online' and location is null) or (mode in ('in_person', 'hybrid') and nullif(btrim(location), '') is not null))", ChatDatabaseTable::MEETINGS));
        DB::statement(sprintf("alter table %s add constraint chat_meetings_status_check check (status in ('scheduled', 'cancelled'))", ChatDatabaseTable::MEETINGS));
        DB::statement(sprintf('alter table %s add constraint chat_meetings_time_check check (ends_at > starts_at)', ChatDatabaseTable::MEETINGS));

        Schema::create(ChatDatabaseTable::MEETING_INVITATIONS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->string('role', 16);
            $table->string('response', 16);
            $table->timestampTz('responded_at')->nullable();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->timestampTz('removed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('meeting_id')->references('id')->on(ChatDatabaseTable::MEETINGS)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('removed_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index(['meeting_id', 'user_id']);
            $table->index(['user_id', 'removed_at']);
        });
        DB::statement(sprintf("alter table %s add constraint chat_meeting_invitations_role_check check (role in ('organizer', 'participant'))", ChatDatabaseTable::MEETING_INVITATIONS));
        DB::statement(sprintf("alter table %s add constraint chat_meeting_invitations_response_check check (response in ('pending', 'accepted', 'declined'))", ChatDatabaseTable::MEETING_INVITATIONS));
        DB::statement(sprintf('create unique index chat_meeting_invitations_active_user_unique on %s (meeting_id, user_id) where removed_at is null', ChatDatabaseTable::MEETING_INVITATIONS));
        DB::statement(sprintf("create unique index chat_meeting_invitations_active_organizer_unique on %s (meeting_id) where removed_at is null and role = 'organizer'", ChatDatabaseTable::MEETING_INVITATIONS));

        Schema::create(ChatDatabaseTable::MEETING_MUTATIONS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('meeting_id');
            $table->date('effective_date');
            $table->string('scope', 16);
            $table->boolean('cancelled')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();
            $table->foreign('meeting_id')->references('id')->on(ChatDatabaseTable::MEETINGS)->restrictOnDelete();
            $table->unique(['meeting_id', 'effective_date', 'scope']);
        });
        DB::statement(sprintf("alter table %s add constraint chat_meeting_mutations_scope_check check (scope in ('occurrence', 'future'))", ChatDatabaseTable::MEETING_MUTATIONS));

        Schema::create(ChatDatabaseTable::MEETING_OCCURRENCES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('meeting_id');
            $table->date('occurrence_date');
            $table->boolean('rtc_enabled');
            $table->string('rtc_room_name', 128)->nullable()->unique();
            $table->string('rtc_status', 16)->nullable();
            $table->boolean('rtc_locked')->default(false);
            $table->timestampTz('rtc_started_at')->nullable();
            $table->timestampTz('rtc_ended_at')->nullable();
            $table->timestampTz('rtc_empty_since')->nullable();
            $table->timestampsTz();
            $table->foreign('meeting_id')->references('id')->on(ChatDatabaseTable::MEETINGS)->restrictOnDelete();
            $table->unique(['meeting_id', 'occurrence_date']);
        });
        DB::statement(sprintf('alter table %s add constraint chat_meeting_occurrences_rtc_check check ((rtc_enabled = false and rtc_room_name is null and rtc_status is null) or rtc_enabled = true)', ChatDatabaseTable::MEETING_OCCURRENCES));

        Schema::create(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('microphone_enabled')->default(true);
            $table->boolean('microphone_allowed')->default(true);
            $table->boolean('camera_enabled')->default(false);
            $table->boolean('screen_sharing')->default(false);
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestampTz('banned_at')->nullable();
            $table->timestampsTz();
            $table->foreign('occurrence_id')->references('id')->on(ChatDatabaseTable::MEETING_OCCURRENCES)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->unique(['occurrence_id', 'user_id']);
            $table->index(['occurrence_id', 'joined_at', 'left_at']);
        });

        Schema::create(ChatDatabaseTable::MEETING_RECORDINGS, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('occurrence_id')->unique();
            $table->unsignedBigInteger('initiated_by_user_id');
            $table->string('status', 24);
            $table->string('file_public_id', 26)->nullable()->unique();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestampTz('retention_removed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestampsTz();
            $table->foreign('occurrence_id')->references('id')->on(ChatDatabaseTable::MEETING_OCCURRENCES)->restrictOnDelete();
            $table->foreign('initiated_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index('status');
        });
        DB::statement(sprintf("alter table %s add constraint chat_meeting_recordings_status_check check (status in ('starting', 'recording', 'pausing', 'paused', 'resuming', 'stopping', 'processing', 'ready', 'failed', 'removed'))", ChatDatabaseTable::MEETING_RECORDINGS));

        Schema::create(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recording_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('egress_id', 128)->unique();
            $table->string('staging_path', 512)->unique();
            $table->string('status', 16);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->foreign('recording_id')->references('id')->on(ChatDatabaseTable::MEETING_RECORDINGS)->restrictOnDelete();
            $table->unique(['recording_id', 'sequence']);
        });
        DB::statement(sprintf("alter table %s add constraint chat_meeting_recording_segments_status_check check (status in ('starting', 'recording', 'stopping', 'processing', 'ready', 'failed'))", ChatDatabaseTable::MEETING_RECORDING_SEGMENTS));

        Schema::create(ChatDatabaseTable::MEETING_RECORDING_SHARES, static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('recording_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('shared_by_user_id');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->foreign('recording_id')->references('id')->on(ChatDatabaseTable::MEETING_RECORDINGS)->restrictOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->foreign('shared_by_user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index(['recipient_user_id', 'revoked_at']);
        });
        DB::statement(sprintf('create unique index chat_meeting_recording_shares_active_unique on %s (recording_id, recipient_user_id) where revoked_at is null', ChatDatabaseTable::MEETING_RECORDING_SHARES));

        Schema::create(ChatDatabaseTable::MEETING_ATTENDANCE, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('occurrence_id');
            $table->unsignedBigInteger('user_id');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestampsTz();
            $table->foreign('occurrence_id')->references('id')->on(ChatDatabaseTable::MEETING_OCCURRENCES)->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on(IdentityDatabaseTable::USERS)->restrictOnDelete();
            $table->index(['occurrence_id', 'user_id']);
        });

        Schema::create(ChatDatabaseTable::SETTINGS, static function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->jsonb('value')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ChatDatabaseTable::SETTINGS);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_ATTENDANCE);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_RECORDING_SHARES);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_RECORDINGS);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_OCCURRENCES);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_MUTATIONS);
        Schema::dropIfExists(ChatDatabaseTable::MEETING_INVITATIONS);
        Schema::dropIfExists(ChatDatabaseTable::MEETINGS);
        Schema::dropIfExists(ChatDatabaseTable::CALL_PREFERENCES);
        Schema::dropIfExists(ChatDatabaseTable::CALL_PARTICIPANTS);
        Schema::dropIfExists(ChatDatabaseTable::CALLS);
        Schema::dropIfExists(ChatDatabaseTable::USER_PRESENCE);
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATION_REALTIME_STATES);
        DB::statement('drop trigger if exists message_deletions_immutable on '.ChatDatabaseTable::MESSAGE_DELETIONS);
        DB::statement('drop trigger if exists message_edit_history_immutable on '.ChatDatabaseTable::MESSAGE_EDIT_HISTORY);
        DB::statement('drop function if exists optional_chat.prevent_message_history_update()');
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_DRAFTS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_ATTACHMENTS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_BOOKMARKS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_PINS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_MENTIONS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_REACTIONS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_DELETIONS);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGE_EDIT_HISTORY);
        Schema::dropIfExists(ChatDatabaseTable::MESSAGES);
        DB::statement('drop trigger if exists conversation_timeline_entries_immutable on '.ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES);
        DB::statement('drop function if exists optional_chat.prevent_timeline_update()');
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES);
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS);
        Schema::dropIfExists(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS);
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATIONS);
    }
};
