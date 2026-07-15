<?php

declare(strict_types=1);

namespace App\Shared\Application\Queries;

/**
 * @template TItem of object
 */
final readonly class PageResult
{
    /**
     * @param  class-string<TItem>  $itemClass
     * @param  list<TItem>  $items
     */
    public function __construct(
        public string $itemClass,
        public array $items,
        public PageMetadata $metadata,
    ) {
        foreach ($items as $item) {
            assert($item instanceof $itemClass);
        }
    }
}
