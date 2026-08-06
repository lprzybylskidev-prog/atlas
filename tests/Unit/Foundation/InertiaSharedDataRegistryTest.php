<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use App\Shared\Presentation\Inertia\Exceptions\DuplicateInertiaSharedData;
use App\Shared\Presentation\Inertia\InertiaSharedDataRegistry;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InertiaSharedDataRegistryTest extends TestCase
{
    #[Test]
    public function it_merges_contributors_deterministically_into_nested_shared_data(): void
    {
        $registry = new InertiaSharedDataRegistry([
            $this->contributor('z.module', ['z.value' => 'last']),
            $this->contributor('a.module', ['a.value' => 'first']),
        ]);

        self::assertSame([
            'a' => ['value' => 'first'],
            'z' => ['value' => 'last'],
        ], $registry->shared(Request::create('/')));
    }

    #[Test]
    public function it_rejects_duplicate_shared_data_paths(): void
    {
        $registry = new InertiaSharedDataRegistry([
            $this->contributor('first.module', ['auth.user' => ['name' => 'A']]),
            $this->contributor('second.module', ['auth.user' => ['name' => 'B']]),
        ]);

        $this->expectException(DuplicateInertiaSharedData::class);

        $registry->shared(Request::create('/'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function contributor(string $key, array $data): InertiaSharedDataContributor
    {
        return new readonly class($key, $data) implements InertiaSharedDataContributor
        {
            /**
             * @param  array<string, mixed>  $data
             */
            public function __construct(
                private string $key,
                private array $data,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function data(Request $request): array
            {
                return $this->data;
            }
        };
    }
}
