<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_composer_package_identity_is_atlas(): void
    {
        $contents = file_get_contents(__DIR__.'/../../composer.json');

        self::assertIsString($contents);

        $composer = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($composer)) {
            self::fail('composer.json must decode to an array.');
        }

        $requirements = $composer['require'] ?? null;

        if (! is_array($requirements)) {
            self::fail('composer.json require section must decode to an array.');
        }

        self::assertSame('lprzybylskidev-prog/atlas', $composer['name'] ?? null);
        self::assertArrayHasKey('laravel/framework', $requirements);
    }
}
