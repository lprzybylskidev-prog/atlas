<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class RuntimeFoundationTest extends TestCase
{
    public function test_release_and_time_foundations_are_configured(): void
    {
        self::assertSame('0.1.0-dev', config('atlas.release.version'));
        self::assertSame('local', config('atlas.release.id'));
        self::assertSame('Europe/Warsaw', config('atlas.time.business_timezone'));
        self::assertSame('UTC', config('atlas.time.technical_storage_timezone'));
    }

    public function test_tailadmin_pro_license_state_is_recorded_as_not_confirmed(): void
    {
        self::assertSame('not_confirmed', config('atlas.ui.tailadmin.pro_license_state'));
        self::assertNull(config('atlas.ui.tailadmin.pro_license_confirmed_at'));
        self::assertNull(config('atlas.ui.tailadmin.pro_license_confirmed_by'));
        self::assertFalse(config('atlas.ui.tailadmin.pro_redistribution_confirmed'));
    }

    public function test_foundation_uses_postgresql_and_redis_runtime_services(): void
    {
        self::assertSame('pgsql', config('database.default'));
        self::assertSame('redis', config('cache.default'));
        self::assertSame('redis', config('queue.default'));
        self::assertSame('redis', config('session.driver'));
    }

    public function test_critical_configuration_validation_rejects_missing_values(): void
    {
        Config::set('atlas.release.id', '');

        $provider = new AppServiceProvider($this->app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Critical configuration [atlas.release.id] must be a non-empty string.');

        $provider->register();
    }
}
