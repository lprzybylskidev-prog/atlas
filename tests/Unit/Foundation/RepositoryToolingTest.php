<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RepositoryToolingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenJavaScriptLockfiles(): iterable
    {
        yield 'npm lockfile' => ['package-lock.json'];
        yield 'npm shrinkwrap' => ['npm-shrinkwrap.json'];
        yield 'yarn lockfile' => ['yarn.lock'];
        yield 'bun text lockfile' => ['bun.lock'];
        yield 'bun binary lockfile' => ['bun.lockb'];
    }

    #[DataProvider('forbiddenJavaScriptLockfiles')]
    public function test_atlas_uses_pnpm_as_the_only_javascript_package_manager(string $lockfile): void
    {
        $basePath = dirname(__DIR__, 3);

        self::assertFileExists($basePath.'/pnpm-lock.yaml');
        self::assertFileDoesNotExist($basePath.'/'.$lockfile);
    }

    public function test_config_environment_variables_are_documented_in_env_example(): void
    {
        $basePath = dirname(__DIR__, 3);
        $usedVariables = $this->configEnvironmentVariables($basePath.'/config');
        $documentedVariables = $this->envExampleVariables($basePath.'/.env.example');

        sort($usedVariables);
        sort($documentedVariables);

        self::assertSame(
            [],
            array_values(array_diff($usedVariables, $documentedVariables)),
            'Every env() variable used by config files must be documented in .env.example.',
        );
    }

    /**
     * @return list<string>
     */
    private function configEnvironmentVariables(string $configPath): array
    {
        $variables = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($candidate->getPathname());

            self::assertIsString($contents);

            preg_match_all("/env\\(['\"]([A-Z0-9_]+)['\"]/", $contents, $matches);

            foreach ($matches[1] as $variable) {
                $variables[] = $variable;
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * @return list<string>
     */
    private function envExampleVariables(string $path): array
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents);

        preg_match_all('/^([A-Z0-9_]+)=/m', $contents, $matches);

        return array_values(array_unique($matches[1]));
    }
}
