<?php

declare(strict_types=1);

namespace App\Shared\Application\Queries;

/**
 * @template TItem of object
 */
final readonly class CursorPageResult
{
    /**
     * @param  class-string<TItem>  $itemClass
     * @param  list<TItem>  $items
     */
    public function __construct(
        public string $itemClass,
        public array $items,
        public ?PageCursor $nextCursor,
        public ?PageCursor $previousCursor = null,
    ) {
        foreach ($items as $item) {
            assert($item instanceof $itemClass);
        }
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
