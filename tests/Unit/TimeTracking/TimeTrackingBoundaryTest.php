<?php

declare(strict_types=1);

namespace Tests\Unit\TimeTracking;

use App\Modules\Optional\TimeTracking\TimeTrackingModule;
use App\Shared\Application\Modules\ModuleKey;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TimeTrackingBoundaryTest extends TestCase
{
    public function test_module_has_no_hr_payroll_leave_or_personnel_dependency(): void
    {
        $dependencyKeys = array_map(
            static fn (ModuleKey $key): string => $key->value,
            (new TimeTrackingModule)->requiredDependencies(),
        );

        self::assertNotContains('hr', $dependencyKeys);
        self::assertNotContains('payroll', $dependencyKeys);
        self::assertNotContains('leave', $dependencyKeys);
        self::assertNotContains('personnel', $dependencyKeys);
    }

    public function test_module_code_does_not_introduce_default_productivity_or_performance_scores(): void
    {
        $basePath = dirname(__DIR__, 3).'/app/Modules/Optional/TimeTracking';
        $forbiddenFragments = [
            'productivity_score',
            'performance_score',
            'employee_rating',
            'disciplinary_score',
        ];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            foreach ($forbiddenFragments as $fragment) {
                self::assertStringNotContainsString(
                    $fragment,
                    strtolower($contents),
                    sprintf('TimeTracking code [%s] must not introduce default employee scoring through [%s].', $candidate->getPathname(), $fragment),
                );
            }
        }
    }
}
