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
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists conversation_timeline_entries_immutable on '.ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES);
        DB::statement('drop function if exists optional_chat.prevent_timeline_update()');
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES);
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS);
        Schema::dropIfExists(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS);
        Schema::dropIfExists(ChatDatabaseTable::CONVERSATIONS);
    }
};
