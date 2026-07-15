<?php

declare(strict_types=1);

namespace App\Shared\Application\Queries;

/**
 * @template TItem of object
 */
final readonly class TypedCollectionResult
{
    /**
     * @param  class-string<TItem>  $itemClass
     * @param  list<TItem>  $items
     */
    public function __construct(
        public string $itemClass,
        public array $items,
    ) {
        foreach ($items as $item) {
            assert($item instanceof $itemClass);
        }
    }

    public function count(): int
    {
        return count($this->items);
    }
}
