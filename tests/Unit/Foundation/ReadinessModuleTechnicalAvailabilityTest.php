<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Health\Application\Readiness\ReadinessCheckResult;
use App\Modules\Core\Health\Application\Readiness\ReadinessReport;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Infrastructure\Modules\ReadinessModuleTechnicalAvailability;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class ReadinessModuleTechnicalAvailabilityTest extends TestCase
{
    public function test_module_without_health_checks_is_available(): void
    {
        $availability = new ReadinessModuleTechnicalAvailability(new StaticReadinessChecker([]));

        self::assertTrue($availability->available(new FakeAvailabilityModuleDefinition('feature_flags')));
    }

    public function test_module_is_available_when_all_declared_readiness_checks_are_healthy(): void
    {
        $availability = new ReadinessModuleTechnicalAvailability(new StaticReadinessChecker([
            ReadinessCheckResult::healthy('clamav', 'ClamAV', false, 'Ready.'),
        ]));

        self::assertTrue($availability->available(new FakeAvailabilityModuleDefinition('files', healthChecks: ['clamav'])));
    }

    public function test_module_stays_available_when_declared_readiness_check_is_degraded(): void
    {
        $availability = new ReadinessModuleTechnicalAvailability(new StaticReadinessChecker([
            ReadinessCheckResult::degraded('meilisearch', 'Meilisearch', false, 'Optional dependency is not reachable.'),
        ]));

        self::assertTrue($availability->available(new FakeAvailabilityModuleDefinition('search', healthChecks: ['meilisearch'])));
    }

    public function test_module_is_unavailable_when_declared_readiness_check_is_unhealthy(): void
    {
        $availability = new ReadinessModuleTechnicalAvailability(new StaticReadinessChecker([
            ReadinessCheckResult::unhealthy('meilisearch', 'Meilisearch', true, 'Critical dependency is not reachable.'),
        ]));

        self::assertFalse($availability->available(new FakeAvailabilityModuleDefinition('search', healthChecks: ['meilisearch'])));
    }

    public function test_module_is_unavailable_when_declared_readiness_check_is_missing(): void
    {
        $availability = new ReadinessModuleTechnicalAvailability(new StaticReadinessChecker([]));

        self::assertFalse($availability->available(new FakeAvailabilityModuleDefinition('files', healthChecks: ['clamav'])));
    }
}

final readonly class StaticReadinessChecker implements ReadinessChecker
{
    /**
     * @param  list<ReadinessCheckResult>  $checks
     */
    public function __construct(private array $checks) {}

    public function check(): ReadinessReport
    {
        return new ReadinessReport($this->checks, CarbonImmutable::parse('2026-08-09 00:00:00', 'UTC'));
    }
}

final readonly class FakeAvailabilityModuleDefinition implements ModuleDefinition
{
    /**
     * @param  list<string>  $healthChecks
     */
    public function __construct(
        private string $key,
        private array $healthChecks = [],
    ) {}

    public function key(): ModuleKey
    {
        return new ModuleKey($this->key);
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Optional;
    }

    public function requiredDependencies(): array
    {
        return [];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return self::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function healthChecks(): array
    {
        return $this->healthChecks;
    }
}
