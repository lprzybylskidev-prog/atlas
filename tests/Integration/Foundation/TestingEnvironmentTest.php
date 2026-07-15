<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use Tests\TestCase;

final class TestingEnvironmentTest extends TestCase
{
    public function test_phpunit_uses_the_dedicated_stateful_test_environment(): void
    {
        self::assertSame('testing', config('app.env'));
        self::assertSame('pgsql', config('database.default'));
        self::assertSame('atlas_testing', config('database.connections.pgsql.database'));
        self::assertSame('redis', config('cache.default'));
        self::assertSame('atlas_testing_cache', config('cache.prefix'));
        self::assertSame('redis', config('queue.default'));
        self::assertSame('redis', config('session.driver'));
        self::assertSame('2', config('database.redis.default.database'));
        self::assertSame('3', config('database.redis.cache.database'));
    }
}
