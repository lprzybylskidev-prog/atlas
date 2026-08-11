<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeederArchitectureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function seederFiles(): iterable
    {
        foreach (glob(dirname(__DIR__, 3).'/database/seeders/*.php') ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('seederFiles')]
    public function test_seeders_use_application_contracts_instead_of_persistence_shortcuts(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach ([
            'forceFill(',
            'DB::',
            '::query(',
            '\\Infrastructure\\Persistence\\',
            'Spatie\\Permission',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, sprintf('%s contains forbidden seeder shortcut [%s].', basename($path), $forbidden));
        }
    }

    public function test_owner_fixture_builders_are_registered_only_for_non_production_environments(): void
    {
        foreach ([
            'app/Modules/Core/Identity/Presentation/Providers/FortifyServiceProvider.php' => 'VerifiedUserFixtureBuilder::class',
            'app/Modules/Core/Authorization/Presentation/Providers/AuthorizationServiceProvider.php' => 'AuthorizationFixtureBuilder::class',
            'app/Modules/Optional/TimeTracking/Presentation/Providers/TimeTrackingServiceProvider.php' => 'TimeTrackingFixtureBuilder::class',
            'app/Modules/Optional/ManagedProcesses/Presentation/Providers/ManagedProcessesServiceProvider.php' => 'ManagedProcessFixtureBuilder::class',
            'app/Modules/Optional/Imports/Presentation/Providers/ImportsServiceProvider.php' => 'ImportFixtureBuilder::class',
        ] as $relativePath => $binding) {
            $source = file_get_contents(dirname(__DIR__, 3).'/'.$relativePath);
            self::assertIsString($source);
            self::assertMatchesRegularExpression(
                sprintf("/environment\\(\\['local', 'development', 'testing'\\]\\).*?%s/s", preg_quote($binding, '/')),
                $source,
                sprintf('%s must keep %s behind the non-production registration guard.', $relativePath, $binding),
            );
        }
    }
}
