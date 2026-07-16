<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseSchema;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DatabaseSchema::ensure(DatabaseSchema::CORE_AUDIT);

        Schema::create(DatabaseTable::AUDIT_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->timestampTz('occurred_at');
            $table->string('module');
            $table->string('action');
            $table->string('result');
            $table->string('source');
            $table->ulid('actor_public_id')->nullable();
            $table->ulid('actual_actor_public_id')->nullable();
            $table->ulid('impersonated_user_public_id')->nullable();
            $table->string('impersonation_session_id')->nullable();
            $table->string('target_type')->nullable();
            $table->ulid('target_public_id')->nullable();
            $table->string('aggregate_type')->nullable();
            $table->ulid('aggregate_public_id')->nullable();
            $table->ulid('team_public_id')->nullable();
            $table->string('correlation_id')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('before_values')->nullable();
            $table->jsonb('after_values')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_security')->default(false);

            $table->index(['module', 'action']);
            $table->index(['actor_public_id', 'occurred_at']);
            $table->index(['actual_actor_public_id', 'occurred_at']);
            $table->index(['impersonated_user_public_id', 'occurred_at']);
            $table->index(['impersonation_session_id', 'occurred_at']);
            $table->index(['target_public_id', 'occurred_at']);
            $table->index(['team_public_id', 'occurred_at']);
            $table->index(['correlation_id', 'occurred_at']);
            $table->index(['is_security', 'occurred_at']);
        });

        Schema::create(DatabaseTable::AUDIT_SECURITY_EVENTS, function (Blueprint $table): void {
            $table->id();
            $table->ulid('audit_event_public_id')->unique();
            $table->timestampTz('occurred_at');
            $table->string('category');
            $table->string('action');
            $table->string('result');
            $table->ulid('actor_public_id')->nullable();
            $table->ulid('target_public_id')->nullable();
            $table->ulid('team_public_id')->nullable();
            $table->string('correlation_id')->nullable();

            $table->foreign('audit_event_public_id')->references('public_id')->on(DatabaseTable::AUDIT_EVENTS)->restrictOnDelete();
            $table->index(['category', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['target_public_id', 'occurred_at']);
            $table->index(['actor_public_id', 'occurred_at']);
        });

        DB::statement($this->appendOnlyFunctionSql());
        DB::statement($this->appendOnlyTriggerSql('audit_events', DatabaseTable::AUDIT_EVENTS));
        DB::statement($this->appendOnlyTriggerSql('audit_security_events', DatabaseTable::AUDIT_SECURITY_EVENTS));

        $this->backfillSecurityAuditEvents();
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists audit_events_append_only on '.DatabaseTable::AUDIT_EVENTS);
        DB::statement('drop trigger if exists audit_security_events_append_only on '.DatabaseTable::AUDIT_SECURITY_EVENTS);
        DB::statement('drop function if exists prevent_audit_mutation()');

        Schema::dropIfExists(DatabaseTable::AUDIT_SECURITY_EVENTS);
        Schema::dropIfExists(DatabaseTable::AUDIT_EVENTS);
    }

    private function appendOnlyFunctionSql(): string
    {
        return <<<'SQL'
create or replace function prevent_audit_mutation()
returns trigger as $$
begin
    raise exception 'Audit records are append-only';
end;
$$ language plpgsql
SQL;
    }

    private function appendOnlyTriggerSql(string $triggerBaseName, string $table): string
    {
        return sprintf(
            'create trigger %s_append_only before update or delete on %s for each row execute function prevent_audit_mutation()',
            $triggerBaseName,
            $table,
        );
    }

    private function backfillSecurityAuditEvents(): void
    {
        if (! Schema::hasTable('public.security_audit_events')) {
            return;
        }

        $auditEventsTable = DatabaseTable::AUDIT_EVENTS;
        $auditSecurityEventsTable = DatabaseTable::AUDIT_SECURITY_EVENTS;
        $legacySecurityAuditEventsTable = 'public.security_audit_events';

        DB::statement(<<<SQL
insert into {$auditEventsTable} (
    public_id,
    occurred_at,
    module,
    action,
    result,
    source,
    actor_public_id,
    target_public_id,
    reason,
    metadata,
    is_security
)
select
    public_id,
    occurred_at,
    module,
    action,
    result,
    source,
    actor_public_id,
    target_public_id,
    reason,
    metadata,
    true
from {$legacySecurityAuditEventsTable}
SQL);

        DB::statement(<<<SQL
insert into {$auditSecurityEventsTable} (
    audit_event_public_id,
    occurred_at,
    category,
    action,
    result,
    actor_public_id,
    target_public_id
)
select
    public_id,
    occurred_at,
    case
        when action like '%login%' then 'authentication'
        when action like '%password%' then 'password'
        when action like '%mfa%' then 'mfa'
        when action like '%session%' then 'session'
        when action like '%role%' or action like '%permission%' or action like '%team%' then 'authorization'
        else 'security'
    end,
    action,
    result,
    actor_public_id,
    target_public_id
from {$legacySecurityAuditEventsTable}
SQL);

        Schema::dropIfExists($legacySecurityAuditEventsTable);
    }
};
