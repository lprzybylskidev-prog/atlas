<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Audit\ConfiguredAuditCatalog;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AuditCatalogArchitectureTest extends TestCase
{
    public function test_catalog_is_non_vacuous_unique_and_uses_canonical_values(): void
    {
        $modules = $this->modules();

        self::assertGreaterThanOrEqual(12, count($modules));
        self::assertGreaterThanOrEqual(100, array_sum(array_map(
            static fn (array $module): int => count($module['actions']),
            $modules,
        )));

        $allActions = [];
        foreach ($modules as $module => $definition) {
            foreach ($definition as $field => $values) {
                self::assertSame($values, array_values(array_unique($values)), $module.'.'.$field);
            }

            foreach ($definition['actions'] as $action) {
                self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/', $action);
                $allActions[$module.'.'.$action] = true;
            }
        }

        self::assertCount(array_sum(array_map(static fn (array $module): int => count($module['actions']), $modules)), $allActions);
    }

    public function test_every_literal_audit_action_is_registered_and_legacy_results_are_absent(): void
    {
        $modules = $this->modules();
        $files = $this->phpFiles($this->basePath().'/app');
        $literalActions = 0;

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression("/result:\\s*'(?:success|blocked|partial)'/", $contents, $file);

            preg_match_all("/module:\\s*'([^']+)'[\\s\\S]{0,180}?action:\\s*'([^']+)'/", $contents, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $literalActions++;
                self::assertContains($match[2], $modules[$match[1]]['actions'] ?? [], $file);
            }
        }

        self::assertGreaterThanOrEqual(25, $literalActions, 'The producer scan must remain non-vacuous.');
    }

    public function test_required_operation_coverage_is_cataloged_and_backed_by_outcome_tests(): void
    {
        $configuration = $this->configuration();
        $modules = $this->modules();
        $coverage = $configuration['required_operation_coverage'] ?? null;
        self::assertIsArray($coverage);
        self::assertGreaterThanOrEqual(6, count($coverage));

        foreach ($coverage as $operation) {
            self::assertIsArray($operation);
            $module = $operation['module'] ?? null;
            $actions = $operation['actions'] ?? null;
            $outcomes = $operation['outcomes'] ?? null;
            $test = $operation['test'] ?? null;
            if (! is_string($module) || ! is_array($actions) || ! is_array($outcomes) || ! is_string($test)) {
                self::fail('Audit coverage entries require a module, action list, outcome list, and test path.');
            }
            self::assertNotSame([], $actions);
            self::assertNotSame([], $outcomes);

            $testPath = $this->basePath().'/'.$test;
            self::assertFileExists($testPath);
            $contents = file_get_contents($testPath);
            self::assertIsString($contents);

            foreach ($actions as $action) {
                if (! is_string($action)) {
                    self::fail('Audit coverage actions must be strings.');
                }
                self::assertContains($action, $modules[$module]['actions'] ?? [], $module);
                self::assertStringContainsString($action, $contents, $test);
            }

            foreach ($outcomes as $outcome) {
                if (! is_string($outcome)) {
                    self::fail('Audit coverage outcomes must be strings.');
                }
                self::assertContains($outcome, ['succeeded', 'rejected', 'failed']);
                self::assertStringContainsString("'".$outcome."'", $contents, $test);
            }
        }
    }

    #[DataProvider('invalidEventProvider')]
    public function test_catalog_rejects_uncataloged_or_unsafe_event_shapes(AuditEvent $event): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConfiguredAuditCatalog($this->modules()))->assertRegistered($event);
    }

    /** @return iterable<string, array{AuditEvent}> */
    public static function invalidEventProvider(): iterable
    {
        yield 'unknown action' => [new AuditEvent('identity', 'auth.unregistered', 'succeeded', 'web')];
        yield 'unknown metadata' => [new AuditEvent('identity', 'auth.login', 'succeeded', 'web', metadata: ['password' => 'secret'])];
        yield 'unknown target type' => [new AuditEvent('identity', 'auth.login', 'succeeded', 'web', targetType: 'database_row')];
    }

    /**
     * @return array<string, array{actions: list<string>, sources: list<string>, target_types: list<string>, aggregate_types: list<string>, metadata_keys: list<string>, security_categories: list<string>}>
     */
    private function modules(): array
    {
        $configuration = $this->configuration();
        $rawModules = $configuration['modules'] ?? null;
        if (! is_array($rawModules)) {
            self::fail('Audit configuration must contain module catalogs.');
        }

        $modules = [];
        foreach ($rawModules as $module => $definition) {
            if (! is_string($module) || ! is_array($definition)) {
                self::fail('Audit module catalog entries must use string keys and array definitions.');
            }

            $modules[$module] = [
                'actions' => $this->stringList($definition['actions'] ?? null),
                'sources' => $this->stringList($definition['sources'] ?? null),
                'target_types' => $this->stringList($definition['target_types'] ?? null),
                'aggregate_types' => $this->stringList($definition['aggregate_types'] ?? null),
                'metadata_keys' => $this->stringList($definition['metadata_keys'] ?? null),
                'security_categories' => $this->stringList($definition['security_categories'] ?? null),
            ];
        }

        return $modules;
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        $configuration = require $this->basePath().'/config/audit.php';
        if (! is_array($configuration)) {
            self::fail('Audit configuration must return an array.');
        }

        $normalized = [];
        foreach ($configuration as $key => $value) {
            if (! is_string($key)) {
                self::fail('Audit configuration top-level keys must be strings.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            self::fail('Audit catalog list value must be an array.');
        }

        $strings = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                self::fail('Audit catalog list entries must be strings.');
            }
            $strings[] = $item;
        }

        return $strings;
    }

    private function basePath(): string
    {
        return dirname(__DIR__, 3);
    }
}
