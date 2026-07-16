<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final readonly class TableColumn
{
    public function __construct(
        public string $key,
        public bool $sortable = true,
        public bool $searchable = true,
        public bool $defaultVisible = true,
    ) {}
}
