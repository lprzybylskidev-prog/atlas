<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Modules\Core\Exports\Application\JsonReportExportGenerator;
use App\Modules\Core\Exports\Application\ReportExportDataProviderRegistry;
use App\Modules\Core\Exports\Application\ReportExportGeneratorRegistry;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileLookup;
use App\Modules\Core\Files\Application\Public\DTOs\FileLifecycleResult;
use App\Modules\Core\Files\Application\Public\DTOs\FileStatus;
use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Optional\Chat\Application\ChatOperationsSummary;
use App\Modules\Optional\Chat\Application\ChatRetention;
use App\Modules\Optional\Chat\Application\Contracts\ChatRetentionStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\Exports\ConversationExportProvider;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchProjectionWriter;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\Enums\ReportExportFormat;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ChatRetentionPrivacyExportTest extends TestCase
{
    use RefreshDatabase;

    private RecordingFileLifecycle $files;

    private RecordingSearchProjectionWriter $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ModuleGate::class, new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return true;
            }
        });
        $this->files = new RecordingFileLifecycle;
        $this->search = new RecordingSearchProjectionWriter;
        $this->app->instance(FileLifecycle::class, $this->files);
        $this->app->instance(FileLookup::class, $this->files);
        $this->app->instance(SearchProjectionWriter::class, $this->search);
        $this->app->forgetInstance(ChatRetention::class);
        $this->app->forgetInstance(ConversationManager::class);
        $this->app->forgetInstance(MessageManager::class);
    }

    public function test_nullable_message_retention_is_indefinite_and_separate_from_recordings(): void
    {
        config(['chat.retention_days' => null, 'chat.recording_retention_days' => 17]);

        self::assertNull($this->app->make(ChatRetention::class)->configuredDays());
        self::assertSame(17, $this->app->make(MeetingRecordingRetention::class)->configuredDays());
        self::assertTrue($this->app->make(ChatRetention::class)->cleanup()['disabled']);

        $this->app->make(ChatRetentionStore::class)->setRetentionDays(9);
        self::assertSame(9, $this->app->make(ChatRetention::class)->configuredDays());
        self::assertSame(17, $this->app->make(MeetingRecordingRetention::class)->configuredDays());
    }

    public function test_core_exports_registers_json_and_the_chat_conversation_provider(): void
    {
        self::assertInstanceOf(JsonReportExportGenerator::class, $this->app->make(ReportExportGeneratorRegistry::class)->get(ReportExportFormat::Json));
        self::assertInstanceOf(ConversationExportProvider::class, $this->app->make(ReportExportDataProviderRegistry::class)->get(ConversationExportProvider::KEY));
    }

    public function test_cleanup_removes_expired_dependants_files_and_search_documents_but_keeps_recent_messages(): void
    {
        [$author, $participant] = User::factory()->count(2)->create()->all();
        $conversation = $this->app->make(ConversationManager::class)->startDirect((string) $author->public_id, (string) $participant->public_id, 'team');
        $messages = $this->app->make(MessageManager::class);
        $expired = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'expired private body', 'expired');
        $recent = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'recent private body', 'recent');
        $expiredIdValue = DB::table(ChatDatabaseTable::MESSAGES)->where('public_id', $expired->publicId)->value('id');
        self::assertIsNumeric($expiredIdValue);
        $expiredId = (int) $expiredIdValue;
        DB::table(ChatDatabaseTable::MESSAGES)->where('id', $expiredId)->update(['created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)]);
        DB::table(ChatDatabaseTable::MESSAGE_REACTIONS)->insert(['message_id' => $expiredId, 'user_id' => $participant->id, 'emoji' => '👍', 'created_at' => now()->subDays(30)]);
        $attachmentPublicId = (string) Str::ulid();
        $filePublicId = (string) Str::ulid();
        DB::table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->insert([
            'public_id' => $attachmentPublicId, 'conversation_id' => DB::table(ChatDatabaseTable::CONVERSATIONS)->where('public_id', $conversation->publicId)->value('id'),
            'uploader_user_id' => $author->id, 'message_id' => $expiredId, 'file_public_id' => $filePublicId, 'kind' => 'voice',
            'original_name' => 'voice.webm', 'mime_type' => 'audio/webm', 'size_bytes' => 123, 'duration_seconds' => 4,
            'attached_at' => now()->subDays(31), 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31),
        ]);
        $this->app->make(ChatRetentionStore::class)->setRetentionDays(30);

        $result = $this->app->make(ChatRetention::class)->cleanup(new DateTimeImmutable);

        self::assertSame(1, $result['removed']);
        self::assertSame([$filePublicId], $this->files->deleted);
        self::assertFalse(DB::table(ChatDatabaseTable::MESSAGES)->where('public_id', $expired->publicId)->exists());
        self::assertTrue(DB::table(ChatDatabaseTable::MESSAGES)->where('public_id', $recent->publicId)->exists());
        self::assertFalse(DB::table(ChatDatabaseTable::MESSAGE_REACTIONS)->where('message_id', $expiredId)->exists());
        self::assertContains('message-'.$expired->publicId, $this->search->deleted);
        self::assertContains('attachment-'.$attachmentPublicId, $this->search->deleted);
    }

    public function test_exports_reauthorize_participant_visibility_preserve_inactive_identity_and_deny_admin_bypass(): void
    {
        [$author, $participant, $administrator] = User::factory()->count(3)->create()->all();
        $author->forceFill(['name' => 'Historical Employee'])->save();
        $conversation = $this->app->make(ConversationManager::class)->startDirect((string) $author->public_id, (string) $participant->public_id, 'team');
        $messages = $this->app->make(MessageManager::class);
        $visible = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'participant-visible', 'visible');
        $hidden = $messages->send((string) $author->public_id, 'team', $conversation->publicId, 'delete-for-me', 'hidden');
        $messages->deleteForMe((string) $participant->public_id, 'team', $conversation->publicId, $hidden->publicId);
        $author->forceFill(['is_active' => false])->save();
        $provider = $this->app->make(ConversationExportProvider::class);
        $rows = iterator_to_array($provider->rows($this->request((string) $participant->public_id, $conversation->publicId)), false);

        self::assertCount(1, $rows);
        self::assertSame($visible->publicId, $rows[0]['message_public_id']);
        self::assertIsString($rows[0]['author']);
        self::assertStringContainsString('Historical Employee', $rows[0]['author']);
        self::assertStringContainsString('Inactive', $rows[0]['author']);

        $this->expectException(RuntimeException::class);
        iterator_to_array($provider->rows($this->request((string) $administrator->public_id, $conversation->publicId)), false);
    }

    public function test_message_bodies_are_not_copied_to_audit(): void
    {
        [$author, $participant] = User::factory()->count(2)->create()->all();
        $conversation = $this->app->make(ConversationManager::class)->startDirect((string) $author->public_id, (string) $participant->public_id, 'team');
        $this->app->make(MessageManager::class)->send((string) $author->public_id, 'team', $conversation->publicId, 'never copy this body into Audit', 'audit-body');

        self::assertStringNotContainsString('never copy this body into Audit', DB::table(AuditDatabaseTable::AUDIT_EVENTS)->get()->toJson());
    }

    public function test_admin_operations_summary_contains_only_safe_aggregates_and_provider_state(): void
    {
        [$author, $participant] = User::factory()->count(2)->create()->all();
        $conversation = $this->app->make(ConversationManager::class)->startDirect((string) $author->public_id, (string) $participant->public_id, 'team');
        $this->app->make(MessageManager::class)->send((string) $author->public_id, 'team', $conversation->publicId, 'private operations secret', 'operations-secret');

        $summary = $this->app->make(ChatOperationsSummary::class)->get();
        $encoded = json_encode($summary, JSON_THROW_ON_ERROR);
        $counts = $summary['counts'];
        $health = $summary['health'];
        $transcription = $summary['transcription'];

        self::assertIsArray($counts);
        self::assertIsArray($health);
        self::assertIsArray($transcription);

        self::assertSame(1, $counts['conversations']);
        self::assertSame(1, $counts['messages']);
        self::assertArrayHasKey('rtc', $health);
        self::assertArrayHasKey('provider', $transcription);
        self::assertStringNotContainsString('private operations secret', $encoded);
        self::assertStringNotContainsString((string) $author->public_id, $encoded);
        self::assertStringNotContainsString($conversation->publicId, $encoded);
    }

    private function request(string $userPublicId, string $conversationPublicId): ReportExportGenerationRequest
    {
        return new ReportExportGenerationRequest('request', ConversationExportProvider::KEY, 'Conversation', 'chat', ReportExportFormat::Json, 'team', $userPublicId,
            ['conversation_public_id' => $conversationPublicId], [], [], [], [], null, 'phase-31', 'v1', new DateTimeImmutable('+1 day'), 'en');
    }
}

final class RecordingFileLifecycle implements FileLifecycle, FileLookup
{
    /** @var list<string> */
    public array $deleted = [];

    public function replace(string $publicId, UploadedFile $replacement, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        throw new RuntimeException('Not used.');
    }

    public function delete(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $this->deleted[] = $publicId;

        return new FileLifecycleResult($publicId, 'delete', true);
    }

    public function anonymize(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        throw new RuntimeException('Not used.');
    }

    public function createRetentionCopy(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        throw new RuntimeException('Not used.');
    }

    public function createRetentionExport(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        throw new RuntimeException('Not used.');
    }

    public function status(string $publicId): FileStatus
    {
        return new FileStatus($publicId, 'file', 'application/octet-stream', 1, FileScanState::Clean, in_array($publicId, $this->deleted, true));
    }

    public function statuses(array $publicIds): array
    {
        $statuses = [];
        foreach (array_values(array_unique($publicIds)) as $publicId) {
            $statuses[$publicId] = $this->status($publicId);
        }

        return $statuses;
    }

    public function displaySummariesForInternalIds(array $fileIds): array
    {
        return [];
    }
}

final class RecordingSearchProjectionWriter implements SearchProjectionWriter
{
    /** @var list<string> */
    public array $deleted = [];

    public function upsert(SearchDocument $document): void {}

    public function delete(string $indexKey, array $documentPublicIds): void
    {
        $this->deleted = [...$this->deleted, ...$documentPublicIds];
    }
}
