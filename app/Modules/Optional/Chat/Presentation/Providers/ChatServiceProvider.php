<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Providers;

use App\Modules\Optional\Chat\Application\AttachmentManager;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\Contracts\AttachmentStore;
use App\Modules\Optional\Chat\Application\Contracts\ChatRealtimePublisher;
use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\MarkdownRenderer;
use App\Modules\Optional\Chat\Application\Contracts\MessageStore;
use App\Modules\Optional\Chat\Application\Contracts\RealtimeStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\MessageManager;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Chat\Application\RealtimeManager;
use App\Modules\Optional\Chat\Application\RtcAccessManager;
use App\Modules\Optional\Chat\Infrastructure\Broadcasting\LaravelChatRealtimePublisher;
use App\Modules\Optional\Chat\Infrastructure\Markdown\SafeMarkdownRenderer;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseAttachmentStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseChatTransaction;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseConversationStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseMessageStore;
use App\Modules\Optional\Chat\Infrastructure\Persistence\DatabaseRealtimeStore;
use App\Modules\Optional\Chat\Infrastructure\Rtc\DenyAllRtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Infrastructure\Rtc\LiveKitRtcGateway;
use App\Modules\Optional\Chat\Infrastructure\Rtc\UnavailableRtcGateway;
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
        $this->app->bind(ConversationStore::class, DatabaseConversationStore::class);
        $this->app->bind(MessageStore::class, DatabaseMessageStore::class);
        $this->app->bind(RealtimeStore::class, DatabaseRealtimeStore::class);
        $this->app->singleton(ChatRealtimePublisher::class, LaravelChatRealtimePublisher::class);
        $this->app->singleton(MarkdownRenderer::class, SafeMarkdownRenderer::class);
        $this->app->singleton(RtcSessionAccessAuthorizer::class, DenyAllRtcSessionAccessAuthorizer::class);
        $this->app->singleton(RtcGateway::class, static fn (): RtcGateway => Config::boolean('livekit.rtc_enabled', false)
            ? new LiveKitRtcGateway(
                serverUrl: Config::string('livekit.server_url'),
                clientUrl: Config::string('livekit.client_url'),
                apiKey: Config::string('livekit.api_key'),
                apiSecret: Config::string('livekit.api_secret'),
                tokenTtlSeconds: Config::integer('livekit.participant_token_ttl_seconds'),
                emptyRoomTimeoutSeconds: Config::integer('livekit.empty_room_timeout_seconds'),
                requestTimeoutSeconds: Config::integer('livekit.request_timeout_seconds'),
            )
            : new UnavailableRtcGateway);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(AttachmentManager::class);
        $this->app->singleton(MessageManager::class);
        $this->app->singleton(RealtimeManager::class);
        $this->app->singleton(RtcAccessManager::class);
        $this->app->tag([ConversationManager::class], 'atlas.team_membership_change_participants');
        $this->app->tag([ChatPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([ChatRouteAvailability::class], 'atlas.inertia_route_availability');
    }
}
