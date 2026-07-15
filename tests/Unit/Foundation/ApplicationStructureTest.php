<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class ApplicationStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function removedLaravelSkeletonDirectories(): iterable
    {
        $basePath = dirname(__DIR__, 3);

        yield 'actions' => [$basePath.'/app/Actions'];
        yield 'controllers' => [$basePath.'/app/Http/Controllers'];
        yield 'models' => [$basePath.'/app/Models'];
        yield 'support' => [$basePath.'/app/Support'];
        yield 'global factories' => [$basePath.'/database/factories'];
        yield 'shell scripts' => [$basePath.'/scripts'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function removedRouteFiles(): iterable
    {
        $basePath = dirname(__DIR__, 3);

        yield 'console route closures' => [$basePath.'/routes/console.php'];
        yield 'monolithic web routes' => [$basePath.'/routes/web.php'];
        yield 'monolithic breadcrumbs' => [$basePath.'/routes/breadcrumbs.php'];
    }

    #[DataProvider('removedLaravelSkeletonDirectories')]
    public function test_default_laravel_application_skeleton_directories_are_not_used(string $path): void
    {
        self::assertDirectoryDoesNotExist($path);
    }

    #[DataProvider('removedRouteFiles')]
    public function test_routes_are_split_by_delivery_area(string $path): void
    {
        self::assertFileDoesNotExist($path);
    }

    public function test_application_code_does_not_reference_removed_laravel_skeleton_namespaces(): void
    {
        $basePath = dirname(__DIR__, 3);
        $forbiddenFragments = [
            'App\\Actions\\',
            'App\\Http\\Controllers\\',
            'App\\Models\\',
            'App\\Support\\',
        ];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath.'/app'));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            foreach ($forbiddenFragments as $fragment) {
                self::assertStringNotContainsString(
                    $fragment,
                    $contents,
                    sprintf('Application file [%s] must not reference the removed Laravel skeleton namespace [%s].', $candidate->getPathname(), $fragment),
                );
            }
        }
    }

    public function test_application_interfaces_live_in_contracts_namespaces(): void
    {
        $basePath = dirname(__DIR__, 3);
        $checkedInterfaces = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath.'/app'));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            if (preg_match('/^interface\s+\w+/m', $contents) !== 1) {
                continue;
            }

            $checkedInterfaces++;

            self::assertStringContainsString(
                '/Contracts/',
                $candidate->getPathname(),
                sprintf('Interface [%s] must live in a local Contracts directory.', $candidate->getPathname()),
            );
            self::assertMatchesRegularExpression(
                '/^namespace\s+.+\\\\Contracts(?:\\\\.+)?;/m',
                $contents,
                sprintf('Interface [%s] must use a Contracts namespace.', $candidate->getPathname()),
            );
        }

        self::assertGreaterThan(0, $checkedInterfaces);
    }

    public function test_application_traits_live_in_concerns_namespaces(): void
    {
        $basePath = dirname(__DIR__, 3);
        $checkedTraits = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath.'/app'));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            if (preg_match('/^trait\s+\w+/m', $contents) !== 1) {
                continue;
            }

            $checkedTraits++;

            self::assertStringContainsString(
                '/Concerns/',
                $candidate->getPathname(),
                sprintf('Trait [%s] must live in a local Concerns directory.', $candidate->getPathname()),
            );
            self::assertMatchesRegularExpression(
                '/^namespace\s+.+\\\\Concerns(?:\\\\.+)?;/m',
                $contents,
                sprintf('Trait [%s] must use a Concerns namespace.', $candidate->getPathname()),
            );
        }

        self::assertGreaterThan(0, $checkedTraits);
    }
}
