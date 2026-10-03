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

    public function groupCandidates(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['users' => $this->conversations->groupCandidates($userPublicId, $teamPublicId)]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'member_public_ids' => ['array'],
            'member_public_ids.*' => ['string', 'size:26'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);
        $members = $this->strings($request->input('member_public_ids', []));
        $group = $this->conversations->createGroup($userPublicId, $teamPublicId, $request->string('name')->toString(), $members);

        return response()->json(['publicId' => $group->publicId, 'type' => $group->type->value], 201);
    }

    public function showGroup(Request $request, string $conversation): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['group' => $this->conversations->groupDetails($userPublicId, $teamPublicId, $conversation)]);
    }

    public function updateGroup(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:rename,add_member,remove_member,transfer_owner,leave'],
            'name' => ['nullable', 'string', 'max:120'],
            'member_public_id' => ['nullable', 'string', 'size:26'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);
        $action = $request->string('action')->toString();
        $memberPublicId = $request->string('member_public_id')->toString();

        match ($action) {
            'rename' => $this->conversations->renameGroup($userPublicId, $teamPublicId, $conversation, $request->string('name')->toString()),
            'add_member' => $this->conversations->addGroupMember($userPublicId, $teamPublicId, $conversation, $memberPublicId),
            'remove_member' => $this->conversations->removeGroupMember($userPublicId, $teamPublicId, $conversation, $memberPublicId),
            'transfer_owner' => $this->conversations->transferGroupOwnership($userPublicId, $teamPublicId, $conversation, $memberPublicId),
            'leave' => $this->conversations->leaveGroup($userPublicId, $teamPublicId, $conversation),
            default => abort(422),
        };

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

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                abort(422);
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
