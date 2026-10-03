<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ChatSearch
{
    public const INDEX_KEY = 'chat.content';

    public function __construct(
        private SearchClient $search,
        private ChatSearchProjectionStore $projection,
        private ConversationManager $conversations,
        private ChatModuleAccess $access,
        private UserLookup $users,
    ) {}

    /**
     * @param  array{author?:string,conversation?:string,date_from?:string,date_to?:string,type?:string}  $filters
     * @return array{items:list<array<string, scalar|null>>,estimatedTotal:int}
     */
    public function query(string $actorPublicId, string $activeTeamPublicId, string $term, array $filters = []): array
    {
        $this->access->ensureAllowed($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::SEARCH_INDEX);
        $actorId = $this->users->internalIdForPublicId($actorPublicId);
        if ($actorId === null) {
            return ['items' => [], 'estimatedTotal' => 0];
        }

        $permissionKeys = [ChatPermissionCatalog::SEARCH_INDEX];
        if ($this->access->allows($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::TRANSCRIPTION_SHOW)) {
            $permissionKeys[] = ChatPermissionCatalog::TRANSCRIPTION_SHOW;
        }

        $searchFilters = array_filter([
            'author_name' => $filters['author'] ?? null,
            'conversation_public_id' => $filters['conversation'] ?? null,
            'occurred_at__gte' => isset($filters['date_from']) ? $this->businessDateBoundary($filters['date_from'], false) : null,
            'occurred_at__lte' => isset($filters['date_to']) ? $this->businessDateBoundary($filters['date_to'], true) : null,
            'result_type' => $filters['type'] ?? null,
        ], static fn (?string $value): bool => is_string($value) && $value !== '');

        $result = $this->search->search(new SearchQuery(
            indexKey: self::INDEX_KEY,
            term: $term,
            activeTeamPublicId: $activeTeamPublicId,
            userPublicId: $actorPublicId,
            permissionKeys: $permissionKeys,
            limit: 100,
            filters: $searchFilters,
        ));

        $items = [];
        foreach ($result->hits as $hit) {
            $candidate = $this->projection->resolve($hit->publicId, $actorId);
            if ($candidate === null) {
                continue;
            }
            if ($candidate->conversationPublicId !== null
                && ! $this->conversations->canAccess($actorPublicId, $activeTeamPublicId, $candidate->conversationPublicId)) {
                continue;
            }
            if ($candidate->type === 'transcript'
                && ! $this->access->allows($actorPublicId, $activeTeamPublicId, ChatPermissionCatalog::TRANSCRIPTION_SHOW)) {
                continue;
            }

            $items[] = $candidate->toArray();
        }

        return ['items' => $items, 'estimatedTotal' => count($items)];
    }

    private function businessDateBoundary(string $date, bool $endOfDay): string
    {
        $time = $endOfDay ? '23:59:59' : '00:00:00';

        return (new DateTimeImmutable($date.' '.$time, new DateTimeZone('Europe/Warsaw')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
