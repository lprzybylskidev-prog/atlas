<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

final class TestingEnvironmentContractTest extends TestCase
{
    public function test_phpunit_has_separate_unit_integration_and_feature_suites(): void
    {
        $configuration = $this->phpunitConfiguration();
        $suiteNames = [];

        foreach ($configuration->testsuites->testsuite as $suite) {
            $suiteNames[] = (string) $suite['name'];
        }

        self::assertSame(['Unit', 'Integration', 'Feature'], $suiteNames);
    }

    public function test_phpunit_stateful_environment_is_isolated_from_development_state(): void
    {
        $environment = $this->phpunitEnvironment();

        self::assertSame('atlas_testing', $environment['DB_DATABASE'] ?? null);
        self::assertSame('atlas_testing_cache', $environment['CACHE_PREFIX'] ?? null);
        self::assertSame('2', $environment['REDIS_DB'] ?? null);
        self::assertSame('3', $environment['REDIS_CACHE_DB'] ?? null);
    }

    public function test_playwright_uses_dedicated_servers_and_state(): void
    {
        $configuration = $this->readFile('playwright.config.ts');

        self::assertStringContainsString('http://127.0.0.1:8010', $configuration);
        self::assertStringContainsString('http://127.0.0.1:5174', $configuration);
        self::assertStringContainsString("DB_DATABASE: 'atlas_e2e'", $configuration);
        self::assertStringContainsString("CACHE_PREFIX: 'atlas_e2e_cache'", $configuration);
        self::assertStringContainsString("REDIS_DB: '4'", $configuration);
        self::assertStringContainsString("REDIS_CACHE_DB: '5'", $configuration);
        self::assertStringContainsString('ensure-test-databases.sh e2e', $configuration);
        self::assertStringContainsString('config:clear', $configuration);
        self::assertStringContainsString('migrate:fresh --force', $configuration);
        self::assertStringContainsString('cd public && php -S 127.0.0.1:8010', $configuration);
        self::assertStringNotContainsString('php artisan serve', $configuration);
        self::assertStringContainsString('reuseExistingServer: false', $configuration);
        self::assertStringNotContainsString('127.0.0.1:8000', $configuration);
        self::assertStringNotContainsString('127.0.0.1:5173', $configuration);
    }

    public function test_public_test_commands_prepare_only_their_required_stateful_lanes(): void
    {
        $scripts = $this->composerScripts();

        self::assertSame(['bash tools/testing/ensure-test-databases.sh phpunit'], $scripts['test:prepare']);
        self::assertContains('@test:prepare', $scripts['test']);
        self::assertSame(['@php artisan test --testsuite=Unit'], $scripts['test:unit']);
        self::assertSame(['@test:prepare', '@php artisan test --testsuite=Integration'], $scripts['test:integration']);
        self::assertSame(['@test:prepare', '@php artisan test --testsuite=Feature'], $scripts['test:feature']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function composerScripts(): array
    {
        $composer = json_decode($this->readFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);
        self::assertArrayHasKey('scripts', $composer);
        self::assertIsArray($composer['scripts']);

        $scripts = [];

        foreach ($composer['scripts'] as $name => $script) {
            self::assertIsString($name);
            self::assertIsArray($script);

            $commands = [];

            foreach ($script as $command) {
                self::assertIsString($command);
                $commands[] = $command;
            }

            $scripts[$name] = $commands;
        }

        return $scripts;
    }

    /**
     * @return array<string, string>
     */
    private function phpunitEnvironment(): array
    {
        $environment = [];

        foreach ($this->phpunitConfiguration()->php->env as $entry) {
            $environment[(string) $entry['name']] = (string) $entry['value'];
        }

        return $environment;
    }

    private function phpunitConfiguration(): SimpleXMLElement
    {
        return new SimpleXMLElement($this->readFile('phpunit.xml'));
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3).'/'.$path);

        self::assertIsString($contents);

        return $contents;
    }
}
