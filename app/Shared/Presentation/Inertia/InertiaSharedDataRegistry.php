<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia;

use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use App\Shared\Presentation\Inertia\Exceptions\DuplicateInertiaSharedData;
use Illuminate\Http\Request;

final readonly class InertiaSharedDataRegistry
{
    /**
     * @param  iterable<mixed>  $contributors
     */
    public function __construct(
        private iterable $contributors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function shared(Request $request): array
    {
        $data = $this->emptySharedData();
        $owners = [];
        $contributors = [];

        foreach ($this->contributors as $contributor) {
            if ($contributor instanceof InertiaSharedDataContributor) {
                $contributors[] = $contributor;
            }
        }

        usort(
            $contributors,
            static fn (InertiaSharedDataContributor $first, InertiaSharedDataContributor $second): int => $first->key() <=> $second->key(),
        );

        foreach ($contributors as $contributor) {
            foreach ($contributor->data($request) as $path => $value) {
                if (isset($owners[$path])) {
                    throw DuplicateInertiaSharedData::forPath($path, $owners[$path], $contributor->key());
                }

                $owners[$path] = $contributor->key();
                $data = $this->withNestedValue($data, $this->pathSegments($path), $value);
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withNestedValue(array $data, array $segments, mixed $value): array
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return $data;
        }

        if ($segments === []) {
            $data[$segment] = $value;

            return $data;
        }

        $child = $data[$segment] ?? [];
        $childData = [];

        if (is_array($child)) {
            foreach ($child as $childKey => $childValue) {
                if (is_string($childKey)) {
                    $childData[$childKey] = $childValue;
                }
            }
        }

        $data[$segment] = $this->withNestedValue($childData, $segments, $value);

        return $data;
    }

    /**
     * @return list<string>
     */
    private function pathSegments(string $path): array
    {
        $segments = [];

        foreach (explode('.', $path) as $segment) {
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySharedData(): array
    {
        return [];
    }
}
