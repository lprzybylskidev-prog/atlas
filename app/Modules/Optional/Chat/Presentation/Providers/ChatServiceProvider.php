<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Providers;

use App\Modules\Optional\Chat\Application\AttachmentManager;
use App\Modules\Optional\Chat\Application\CallManager;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\Contracts\CallStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingAssembler;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\Contracts\RealtimeStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionStore;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MeetingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingAccessManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingFinalizer;
use App\Modules\Optional\Chat\Application\MeetingRecordingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetentionProcess;
use App\Modules\Optional\Chat\Application\MeetingRtcMaintenance;
use App\Modules\Optional\Chat\Application\MeetingRtcManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Application\RealtimeManager;
use App\Modules\Optional\Chat\Application\RtcAccessManager;
use App\Modules\Optional\Chat\Application\TranscriptionManager;
use App\Modules\Optional\Chat\Application\TranscriptionProcess;
use App\Modules\Optional\Chat\Application\TranscriptionProcessor;
use App\Modules\Optional\Chat\Infrastructure\Broadcasting\LaravelChatRealtimePublisher;
use App\Modules\Optional\Chat\Infrastructure\Markdown\SafeMarkdownRenderer;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseAttachmentStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseCallStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseChatTransaction;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseConversationStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseMeetingRecordingStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseMeetingStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseMessageStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseRealtimeStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseTranscriptionStore;
use App\Modules\Optional\Chat\Infrastructure\Rtc\DatabaseCallSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Infrastructure\Rtc\FfmpegMeetingRecordingAssembler;
use App\Modules\Optional\Chat\Infrastructure\Rtc\LiveKitRtcGateway;
use App\Modules\Optional\Chat\Infrastructure\Rtc\UnavailableRtcGateway;
use App\Modules\Optional\Chat\Infrastructure\Runtime\MeetingRecordingRetentionProcessHandler;
use App\Modules\Optional\Chat\Infrastructure\Runtime\TranscriptionProcessHandler;
use App\Modules\Optional\Chat\Infrastructure\Transcription\DeterministicTranscriptionProvider;
use App\Modules\Optional\Chat\Infrastructure\Transcription\UnavailableTranscriptionProvider;
use App\Modules\Optional\Chat\Presentation\Console\FinalizeMeetingRecordingsCommand;
use App\Modules\Optional\Chat\Presentation\Console\PruneMeetingRecordingsCommand;
use App\Modules\Optional\Chat\Presentation\Inertia\ChatRouteAvailability;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatModuleAccess::class);
        $this->app->bind(ChatTransaction::class, DatabaseChatTransaction::class);
        $this->app->bind(AttachmentStore::class, DatabaseAttachmentStore::class);
        $this->app->bind(CallStore::class, DatabaseCallStore::class);
        $this->app->bind(ConversationStore::class, DatabaseConversationStore::class);
        $this->app->bind(MessageStore::class, DatabaseMessageStore::class);
        $this->app->bind(MeetingStore::class, DatabaseMeetingStore::class);
        $this->app->bind(MeetingRecordingStore::class, DatabaseMeetingRecordingStore::class);
        $this->app->bind(TranscriptionStore::class, DatabaseTranscriptionStore::class);
        $this->app->singleton(TranscriptionProvider::class, static function (): TranscriptionProvider {
            $testDriver = Config::get('transcription.driver') === 'deterministic';

            return app()->environment('testing') && Config::boolean('transcription.enabled', false) && $testDriver
                ? new DeterministicTranscriptionProvider
                : new UnavailableTranscriptionProvider;
        });
        $this->app->singleton(MeetingRecordingAssembler::class, static fn (): MeetingRecordingAssembler => new FfmpegMeetingRecordingAssembler(
            Config::string('livekit.egress_staging_directory'),
            Config::string('livekit.ffmpeg_binary'),
        ));
        $this->app->bind(RealtimeStore::class, DatabaseRealtimeStore::class);
        $this->app->singleton(ChatRealtimePublisher::class, LaravelChatRealtimePublisher::class);
        $this->app->singleton(MarkdownRenderer::class, SafeMarkdownRenderer::class);
        $this->app->singleton(RtcSessionAccessAuthorizer::class, DatabaseCallSessionAccessAuthorizer::class);
        $this->app->singleton(RtcGateway::class, static fn (): RtcGateway => Config::boolean('livekit.rtc_enabled', false)
            ? new LiveKitRtcGateway(
                serverUrl: Config::string('livekit.server_url'),
                clientUrl: Config::string('livekit.client_url'),
                apiKey: Config::string('livekit.api_key'),
                apiSecret: Config::string('livekit.api_secret'),
                tokenTtlSeconds: Config::integer('livekit.participant_token_ttl_seconds'),
                emptyRoomTimeoutSeconds: Config::integer('livekit.empty_room_timeout_seconds'),
                requestTimeoutSeconds: Config::integer('livekit.request_timeout_seconds'),
                egressEnabled: Config::boolean('livekit.egress_enabled', false),
                recordingTemplateUrl: Config::string('livekit.recording_template_url'),
                egressOutputDirectory: Config::string('livekit.egress_output_directory'),
            )
            : new UnavailableRtcGateway);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(CallManager::class);
        $this->app->singleton(AttachmentManager::class);
        $this->app->singleton(MessageManager::class);
        $this->app->singleton(MeetingManager::class);
        $this->app->singleton(MeetingRtcManager::class);
        $this->app->singleton(MeetingRecordingManager::class);
        $this->app->singleton(MeetingRecordingFinalizer::class);
        $this->app->singleton(MeetingRecordingAccessManager::class);
        $this->app->singleton(MeetingRecordingRetention::class);
        $this->app->singleton(MeetingRtcMaintenance::class);
        $this->app->singleton(RealtimeManager::class);
        $this->app->singleton(RtcAccessManager::class);
        $this->app->singleton(TranscriptionManager::class);
        $this->app->singleton(TranscriptionProcessor::class);
        $this->app->bind('chat.managed_process.recording_retention_definition', fn () => MeetingRecordingRetentionProcess::definition());
        $this->app->bind('chat.managed_process.transcription_definition', fn () => TranscriptionProcess::definition());
        $this->app->tag([ConversationManager::class], 'atlas.team_membership_change_participants');
        $this->app->tag([ChatPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ChatRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag(['chat.managed_process.recording_retention_definition'], 'atlas.managed_process_definitions');
        $this->app->tag(['chat.managed_process.transcription_definition'], 'atlas.managed_process_definitions');
        $this->app->tag([MeetingRecordingRetentionProcessHandler::class, TranscriptionProcessHandler::class], 'atlas.managed_process_handlers');
    }

    public function boot(): void
    {
        $this->commands([FinalizeMeetingRecordingsCommand::class, PruneMeetingRecordingsCommand::class]);
    }
}
