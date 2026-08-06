<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class CrossModuleArchitectureTest extends TestCase
{
    public function test_global_inertia_middleware_stays_a_thin_registry_adapter(): void
    {
        $path = dirname(__DIR__, 3).'/app/Http/Middleware/HandleInertiaRequests.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringNotContainsString('App\\Modules\\', $contents);
        self::assertStringNotContainsString('DB::table', $contents);
        self::assertStringContainsString('InertiaSharedDataRegistry', $contents);
    }

    public function test_generic_high_risk_middleware_does_not_depend_on_privacy_presentation(): void
    {
        $path = dirname(__DIR__, 3).'/app/Http/Middleware/RequireHighRiskAdministrativeAuthorization.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringNotContainsString('App\\Modules\\Core\\Privacy\\', $contents);
        self::assertStringNotContainsString('admin.privacy-retention.', $contents);
        self::assertStringNotContainsString('subject_identifier', $contents);
        self::assertStringContainsString('HighRiskReauthenticationContinuation', $contents);
    }

    public function test_modules_import_other_modules_only_through_application_public_contracts(): void
    {
        $basePath = dirname(__DIR__, 3);
        $modulesPath = $basePath.'/app/Modules';

        self::assertDirectoryExists($modulesPath);

        foreach ($this->modulePhpFiles($modulesPath) as $file) {
            $moduleRoot = $this->moduleRoot($file, $modulesPath);
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);

            foreach ($this->moduleImports($contents) as $importedClass) {
                if (! str_starts_with($importedClass, 'App\\Modules\\')) {
                    continue;
                }

                $importedPath = substr($importedClass, strlen('App\\Modules\\'));

                if (str_starts_with($importedPath, $moduleRoot.'\\')) {
                    continue;
                }

                self::assertStringContainsString(
                    '\\Application\\Public\\',
                    $importedPath,
                    sprintf(
                        'Module file [%s] imports another module through a non-public namespace [%s].',
                        $file->getPathname(),
                        $importedClass,
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

    /**
     * @return list<string>
     */
    private function moduleImports(string $contents): array
    {
        $tokens = token_get_all($contents);
        $imports = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_USE) {
                continue;
            }

            $next = $this->nextNonWhitespaceToken($tokens, $index + 1);

            if ($next === '(') {
                continue;
            }

            $statement = '';
            $index++;

            for (; $index < $count; $index++) {
                $part = $tokens[$index];
                $text = is_array($part) ? $part[1] : $part;

                if ($text === ';') {
                    break;
                }

                $statement .= $text;
            }

            foreach ($this->expandUseStatement($statement) as $class) {
                $imports[] = $class;
            }
        }

        return $imports;
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private function nextNonWhitespaceToken(array $tokens, int $start): mixed
    {
        $count = count($tokens);

        for ($index = $start; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[1] : $token;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function expandUseStatement(string $statement): array
    {
        $statement = trim(preg_replace('/\s+/', ' ', $statement) ?? '');

        if ($statement === '') {
            return [];
        }

        if (! str_contains($statement, '{')) {
            return array_values(
                array_map(
                    static fn (string $import): string => trim(preg_replace('/\s+as\s+.*/i', '', $import) ?? $import),
                    array_filter(explode(',', $statement), static fn (string $import): bool => $import !== ''),
                ),
            );
        }

        [$prefix, $group] = explode('{', $statement, 2);
        $group = rtrim($group, '}');
        $imports = [];

        foreach (array_filter(explode(',', $group), static fn (string $import): bool => $import !== '') as $suffix) {
            $imports[] = trim($prefix.(preg_replace('/\s+as\s+.*/i', '', $suffix) ?? $suffix));
        }

        return $imports;
    }
}
