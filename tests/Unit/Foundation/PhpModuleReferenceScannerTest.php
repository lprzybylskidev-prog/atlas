<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Architecture\PhpModuleReferenceScanner;

final class PhpModuleReferenceScannerTest extends TestCase
{
    #[DataProvider('referenceSyntaxProvider')]
    public function test_scanner_detects_every_supported_module_reference_syntax(string $body, string $expected): void
    {
        $code = "<?php\n".$body;

        self::assertContains($expected, (new PhpModuleReferenceScanner)->referencesInCode($code));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function referenceSyntaxProvider(): iterable
    {
        yield 'simple use' => [
            'use App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup;',
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup',
        ];
        yield 'grouped use' => [
            'use App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\{UserLookup, UserSessionRegistry};',
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserSessionRegistry',
        ];
        yield 'fully qualified new' => [
            '$value = new \\App\\Modules\\Core\\Identity\\Application\\Public\\DTOs\\UserDisplaySummary();',
            'App\\Modules\\Core\\Identity\\Application\\Public\\DTOs\\UserDisplaySummary',
        ];
        yield 'extends' => [
            'class Example extends \\App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User {}',
            'App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User',
        ];
        yield 'implements' => [
            'class Example implements \\App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup {}',
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup',
        ];
        yield 'parameter type' => [
            'function example(\\App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup $users): void {}',
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup',
        ];
        yield 'attribute' => [
            '#[\\App\\Modules\\Core\\Identity\\Presentation\\Attributes\\Example] class Subject {}',
            'App\\Modules\\Core\\Identity\\Presentation\\Attributes\\Example',
        ];
        yield 'static class reference' => [
            '$class = \\App\\Modules\\Core\\Identity\\IdentityModule::class;',
            'App\\Modules\\Core\\Identity\\IdentityModule',
        ];
        yield 'class string' => [
            '$class = \'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup\';',
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup',
        ];
    }
}
