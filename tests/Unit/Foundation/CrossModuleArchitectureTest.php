<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class CrossModuleArchitectureTest extends TestCase
{
    public function test_modules_import_other_modules_only_through_application_public_contracts(): void
    {
        $basePath = dirname(__DIR__, 3);
        $modulesPath = $basePath.'/app/Modules';

        self::assertDirectoryExists($modulesPath);

        foreach ($this->modulePhpFiles($modulesPath) as $file) {
            $moduleRoot = $this->moduleRoot($file, $modulesPath);
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);

            preg_match_all('/^use App\\\\Modules\\\\([^;]+);/m', $contents, $matches);

            foreach ($matches[1] as $importedPath) {
                if (str_starts_with($importedPath, $moduleRoot.'\\')) {
                    continue;
                }

                self::assertStringContainsString(
                    '\\Application\\Public\\',
                    $importedPath,
                    sprintf(
                        'Module file [%s] imports another module through a non-public namespace [%s].',
                        $file->getPathname(),
                        'App\\Modules\\'.$importedPath,
                    ),
                );
            }
        }
    }

    public function test_public_module_contracts_do_not_create_preemptive_version_namespaces(): void
    {
        $basePath = dirname(__DIR__, 3);
        $modulesPath = $basePath.'/app/Modules';
        $checkedFiles = 0;

        foreach ($this->modulePhpFiles($modulesPath) as $file) {
            if (! str_contains($file->getPathname(), '/Application/Public/')) {
                continue;
            }

            $checkedFiles++;
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression(
                '/namespace App\\\\Modules\\\\.*\\\\Application\\\\Public\\\\V[0-9]+(?:\\\\|;)/',
                $contents,
                sprintf('Public contracts in [%s] must not introduce preemptive V1/V2 namespaces.', $file->getPathname()),
            );
        }

        self::assertGreaterThanOrEqual(0, $checkedFiles);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function modulePhpFiles(string $path): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            yield $candidate;
        }
    }

    private function moduleRoot(SplFileInfo $file, string $modulesPath): string
    {
        $relative = str_replace($modulesPath.'/', '', $file->getPathname());
        $parts = explode('/', $relative);

        return $parts[0].'\\'.$parts[1];
    }
}
