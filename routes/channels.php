<?php

declare(strict_types=1);

use App\Modules\Optional\Chat\Application\Exceptions\ChatAccessDenied;
use App\Modules\Optional\Chat\Application\RealtimeManager;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.user.{userPublicId}', function (Authenticatable $user, string $userPublicId): bool {
    $request = request();
    $actorPublicId = data_get($user, 'public_id');
    $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

    if (! is_string($actorPublicId) || ! is_string($teamPublicId)) {
        return false;
    }

    try {
        return app(RealtimeManager::class)->authorizeUserChannel($actorPublicId, $teamPublicId, $userPublicId);
    } catch (ChatAccessDenied|MessageOperationDenied) {
        return false;
    } catch (Throwable $exception) {
        report($exception);

        return false;
    }
});

Broadcast::channel('chat.conversation.{conversationPublicId}', function (Authenticatable $user, string $conversationPublicId): array|false {
    $request = request();
    $actorPublicId = data_get($user, 'public_id');
    $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

    if (! is_string($actorPublicId) || ! is_string($teamPublicId)) {
        return false;
    }

    try {
        return app(RealtimeManager::class)->authorizeConversationChannel($actorPublicId, $teamPublicId, $conversationPublicId);
    } catch (ChatAccessDenied|MessageOperationDenied) {
        return false;
    } catch (Throwable $exception) {
        report($exception);

        return false;
    }
});
