<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileScanner;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Exceptions\FileNotAvailableForDownload;
use App\Modules\Core\Files\Infrastructure\Persistence\TableNames\FilesDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Optional\Chat\Application\AttachmentManager;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationScopePolicy;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class ChatAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('atlas_files');
        Queue::fake();
        Config::set('atlas.files.disk', 'atlas_files');
        Config::set('atlas.files.scanner', 'fake');
        Config::set('atlas.files.fake_scanner_result', 'clean');
        Config::set('atlas.files.allowed_extensions', ['txt', 'webm', 'wav']);
        Config::set('atlas.files.allowed_mime_types', ['text/plain', 'application/octet-stream', 'audio/webm', 'video/webm', 'audio/wav', 'audio/x-wav']);
    }

    public function test_attachment_is_files_owned_quarantined_and_visible_only_after_clean_scan(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $attachments = $this->attachments();
        $attachment = $attachments->upload((string) $author->public_id, 'team', $conversation->publicId, UploadedFile::fake()->createWithContent('notice.txt', 'See https://atlas.example/help'));

        self::assertSame(FileScanState::Pending, $attachment->scanState);
        self::assertFalse($attachment->available());

        $message = $this->messages()->send((string) $author->public_id, 'team', $conversation->publicId, 'See https://atlas.example/help', 'attachment-001', attachmentPublicIds: [$attachment->publicId]);
        self::assertCount(1, $message->attachments);
        $content = $attachments->content((string) $member->public_id, 'team', $conversation->publicId);
        self::assertCount(1, $content->files);
        self::assertSame(['https://atlas.example/help'], $content->links);

        try {
            $attachments->downloadable((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId);
            self::fail('A quarantined Chat attachment was downloadable.');
        } catch (FileNotAvailableForDownload) {
        }

        $attachments->retryScan((string) $author->public_id, 'team', $conversation->publicId, $attachment->publicId);
        self::assertTrue($attachments->status((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId)->available());
        self::assertSame('notice.txt', $attachments->downloadable((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId)->filename);
    }

    public function test_voice_message_has_a_hard_fifteen_minute_limit_and_is_persisted_through_files(): void
    {
        [$author, $member] = User::factory()->count(2)->create()->all();
        $conversation = $this->conversations()->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $attachments = $this->attachments();
        $voice = UploadedFile::fake()->create('voice.webm', 20, 'audio/webm');

        try {
            $attachments->uploadVoice((string) $author->public_id, 'team', $conversation->publicId, $voice, 901);
            self::fail('A voice message longer than 15 minutes was accepted.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, DB::table(FilesDatabaseTable::FILE_OBJECTS)->count());
        }

        $stored = $attachments->uploadVoice((string) $author->public_id, 'team', $conversation->publicId, $this->voiceUpload(), 37);
        $message = $this->messages()->send((string) $author->public_id, 'team', $conversation->publicId, '', 'voice-001', attachmentPublicIds: [$stored->publicId]);

        self::assertSame('voice', $message->attachments[0]->kind->value);
        self::assertSame(37, $message->attachments[0]->durationSeconds);
        self::assertCount(1, $attachments->content((string) $member->public_id, 'team', $conversation->publicId)->media);
        $this->assertDatabaseHas(ChatDatabaseTable::MESSAGE_ATTACHMENTS, [
            'public_id' => $stored->publicId,
            'kind' => 'voice',
            'duration_seconds' => 37,
        ]);
    }

    public function test_non_participants_cross_conversation_claims_and_delete_for_me_side_doors_are_denied(): void
    {
        [$author, $member, $outsider] = User::factory()->count(3)->create()->all();
        $conversations = $this->conversations();
        $conversation = $conversations->startDirect((string) $author->public_id, (string) $member->public_id, 'team');
        $other = $conversations->startDirect((string) $author->public_id, (string) $outsider->public_id, 'team');
        $attachments = $this->attachments();
        $attachment = $attachments->upload((string) $author->public_id, 'team', $conversation->publicId, UploadedFile::fake()->createWithContent('private.txt', 'private'));

        $deniedOperations = 0;

        foreach ([
            fn () => $attachments->status((string) $outsider->public_id, 'team', $conversation->publicId, $attachment->publicId),
            fn () => $attachments->status((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId),
            fn () => $attachments->retryScan((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId),
            fn () => $attachments->retryScan((string) $outsider->public_id, 'team', $conversation->publicId, $attachment->publicId),
            fn () => $this->messages()->send((string) $author->public_id, 'team', $other->publicId, '', 'cross-attachment', attachmentPublicIds: [$attachment->publicId]),
        ] as $operation) {
            try {
                $operation();
                self::fail('An unauthorized Chat attachment operation was accepted.');
            } catch (MessageOperationDenied) {
                $deniedOperations++;
            }
        }

        self::assertSame(5, $deniedOperations);

        $discarded = $attachments->upload((string) $author->public_id, 'team', $conversation->publicId, UploadedFile::fake()->createWithContent('discard.txt', 'discard'));
        $attachments->discard((string) $author->public_id, 'team', $conversation->publicId, $discarded->publicId);
        self::assertNotNull(DB::table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->where('public_id', $discarded->publicId)->value('discarded_at'));
        self::assertNotNull(DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $discarded->filePublicId)->value('deleted_at'));

        $message = $this->messages()->send((string) $author->public_id, 'team', $conversation->publicId, 'https://private.example', 'private-attachment', attachmentPublicIds: [$attachment->publicId]);
        $this->messages()->deleteForMe((string) $member->public_id, 'team', $conversation->publicId, $message->publicId);

        self::assertSame([], $attachments->content((string) $member->public_id, 'team', $conversation->publicId)->links);
        $this->expectException(MessageOperationDenied::class);
        $attachments->status((string) $member->public_id, 'team', $conversation->publicId, $attachment->publicId);
    }

    private function attachments(): AttachmentManager
    {
        return new AttachmentManager(
            attachments: $this->app->make(AttachmentStore::class),
            conversations: $this->app->make(ConversationStore::class),
            messages: $this->app->make(MessageStore::class),
            conversationManager: $this->conversations(),
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            teams: $this->app->make(TeamLookup::class),
            files: $this->app->make(FileStorage::class),
            scanner: $this->app->make(FileScanner::class),
            lifecycle: $this->app->make(FileLifecycle::class),
        );
    }

    private function voiceUpload(): UploadedFile
    {
        $wav = 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', 0);

        return UploadedFile::fake()->createWithContent('voice.wav', $wav);
    }

    private function messages(): MessageManager
    {
        return new MessageManager(
            messages: $this->app->make(MessageStore::class),
            conversations: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            scopePolicy: new ConversationScopePolicy,
            markdown: $this->app->make(MarkdownRenderer::class),
            attachments: $this->app->make(AttachmentStore::class),
        );
    }

    private function conversations(): ConversationManager
    {
        return new ConversationManager(
            store: $this->app->make(ConversationStore::class),
            transaction: $this->app->make(ChatTransaction::class),
            access: new ChatModuleAccess($this->gate()),
            users: $this->app->make(UserLookup::class),
            teams: $this->app->make(TeamLookup::class),
            teamMemberships: $this->app->make(UserTeamMembershipManager::class),
            scopePolicy: new ConversationScopePolicy,
            audit: $this->app->make(AuditRecorder::class),
        );
    }

    private function gate(): ModuleGate
    {
        return new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return true;
            }
        };
    }
}
