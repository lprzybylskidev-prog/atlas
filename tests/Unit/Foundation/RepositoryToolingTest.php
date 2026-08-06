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

    public function test_env_example_does_not_define_duplicate_active_keys(): void
    {
        $basePath = dirname(__DIR__, 3);
        $lines = file($basePath.'/.env.example');

        self::assertIsArray($lines);

        $seen = [];
        $duplicates = [];

        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            $seen[$key] ??= [];
            $seen[$key][] = $lineNumber + 1;

            if (count($seen[$key]) > 1) {
                $duplicates[$key] = $seen[$key];
            }
        }

        self::assertSame([], $duplicates, 'Duplicate active .env.example keys must be removed.');
    }

    public function test_public_phpstan_runner_covers_every_configured_php_path(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && bash tools/quality/run-phpstan.sh --verify-coverage';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_playwright_versions_stay_aligned_across_package_lockfile_and_dev_container(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && bash tools/quality/check-playwright-version.sh';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
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
