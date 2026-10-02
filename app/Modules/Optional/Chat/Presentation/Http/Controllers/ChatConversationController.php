<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\ConversationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatConversationController
{
    public function __construct(private ConversationManager $conversations) {}

    public function storeDirect(Request $request): JsonResponse
    {
        $values = $request->validate(['target_user_public_id' => ['required', 'string', 'size:26']]);
        $targetUserPublicId = is_array($values) ? ($values['target_user_public_id'] ?? null) : null;
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId) || ! is_string($targetUserPublicId)) {
            abort(403);
        }

        $conversation = $this->conversations->startDirect($userPublicId, $targetUserPublicId, $teamPublicId);

        return response()->json([
            'publicId' => $conversation->publicId,
            'type' => $conversation->type->value,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['conversations' => $this->conversations->listFor($userPublicId, $teamPublicId)]);
    }

    public function favorite(Request $request, string $conversation): JsonResponse
    {
        $request->validate(['favorite' => ['required', 'boolean']]);
        [$userPublicId, $teamPublicId] = $this->context($request);
        $this->conversations->setFavorite($userPublicId, $teamPublicId, $conversation, $request->boolean('favorite'));

        return response()->json(['ok' => true]);
    }

    public function showTeam(Request $request): JsonResponse
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            abort(403);
        }

        $conversation = $this->conversations->synchronizeTeamConversationRecord($teamPublicId);

        if (! $this->conversations->canAccess($userPublicId, $teamPublicId, $conversation->publicId)) {
            abort(403);
        }

        return response()->json([
            'publicId' => $conversation->publicId,
            'type' => $conversation->type->value,
        ]);
    }

    /** @return array{string,string} */
    private function context(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            abort(403);
        }

        return [$userPublicId, $teamPublicId];
    }
}
