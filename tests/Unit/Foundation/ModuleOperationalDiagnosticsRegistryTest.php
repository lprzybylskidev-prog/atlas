<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Modules\Contracts\ModuleOperationalDiagnostics;
use App\Shared\Application\Modules\ModuleOperationalDiagnosticsRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleOperationalDiagnosticsRegistryTest extends TestCase
{
    public function test_it_resolves_issues_by_module_key_and_uses_empty_fallback(): void
    {
        $registry = new ModuleOperationalDiagnosticsRegistry([
            new class implements ModuleOperationalDiagnostics
            {
                public function moduleKey(): string
                {
                    return 'files';
                }

                public function issues(): array
                {
                    return [[
                        'severity' => 'degraded',
                        'label' => 'Blocked files',
                        'description' => 'File scan states are blocking file use and need review.',
                        'value' => 2,
                    ]];
                }
            },
        ]);

        self::assertSame([
            [
                'severity' => 'degraded',
                'label' => 'Blocked files',
                'description' => 'File scan states are blocking file use and need review.',
                'value' => 2,
            ],
        ], $registry->issuesFor('files'));
        self::assertSame([], $registry->issuesFor('unknown'));
    }
}
