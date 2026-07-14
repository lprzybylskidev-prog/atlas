<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class ComposableViewArchitectureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function composableViewFiles(): iterable
    {
        $basePath = dirname(__DIR__, 3);
        $roots = [
            $basePath.'/resources/js/Components/ComposableView',
            $basePath.'/resources/js/Services',
            $basePath.'/resources/js/Types/composable-view.ts',
        ];

        foreach ($roots as $root) {
            $file = new SplFileInfo($root);

            if ($file->isFile()) {
                yield $file->getFilename() => [$file->getPathname()];

                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $candidate) {
                if (! $candidate instanceof SplFileInfo || ! $candidate->isFile()) {
                    continue;
                }

                if (! in_array($candidate->getExtension(), ['ts', 'vue'], true)) {
                    continue;
                }

                yield str_replace($basePath.DIRECTORY_SEPARATOR, '', $candidate->getPathname()) => [$candidate->getPathname()];
            }
        }
    }

    #[DataProvider('composableViewFiles')]
    public function test_composable_view_frontend_does_not_import_backend_or_module_internals(string $path): void
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents);

        $forbiddenFragments = [
            'App\\Models',
            'Illuminate\\Database\\Eloquent',
            '/Domain/',
            '/Infrastructure/',
            '../Domain/',
            '../Infrastructure/',
            'Modules/',
        ];

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString(
                $fragment,
                $contents,
                sprintf('Composable view file [%s] must not import backend or module internals.', $path),
            );
        }
    }
}
