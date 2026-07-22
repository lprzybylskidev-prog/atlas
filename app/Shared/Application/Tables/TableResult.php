<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final readonly class TableResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $savedViews
     */
    public function __construct(
        public array $rows,
        public int $total,
        public TableState $state,
        public array $savedViews = [],
    ) {}

    /**
     * @param  array{endpoint: string, formats: list<string>}|null  $exports
     * @return array<string, mixed>
     */
    public function tableMeta(string $key, ?array $exports = null): array
    {
        $from = $this->total === 0 ? 0 : (($this->state->page - 1) * $this->state->perPage) + 1;
        $to = min($this->total, $this->state->page * $this->state->perPage);

        $meta = [
            'key' => $key,
            'state' => $this->state->toArray(),
            'pagination' => [
                'total' => $this->total,
                'page' => $this->state->page,
                'perPage' => $this->state->perPage,
                'from' => $from,
                'to' => $to,
            ],
            'savedViews' => $this->savedViews,
        ];

        if ($exports !== null) {
            $meta['exports'] = $exports;
        }

        return $meta;
    }

    /**
     * @param  list<array<string, mixed>>  $savedViews
     */
    public function withSavedViews(array $savedViews): self
    {
        return new self($this->rows, $this->total, $this->state, $savedViews);
    }
}
