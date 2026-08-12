<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Presentation\Localization\AtlasTranslationUsageInventory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalizationUsageInventoryTest extends TestCase
{
    public function test_backend_rendered_translation_usage_exists_in_both_catalogs(): void
    {
        $inventory = AtlasTranslationUsageInventory::inventory(dirname(__DIR__, 3));

        self::assertGreaterThanOrEqual(150, count($inventory), 'The backend translation inventory must remain non-vacuous.');
        self::assertSame([], AtlasTranslationUsageInventory::missingKeys($inventory, $this->catalog('pl')));
        self::assertSame([], AtlasTranslationUsageInventory::missingKeys($inventory, $this->catalog('en')));
    }

    #[DataProvider('locales')]
    public function test_removing_a_used_translation_from_a_locale_fails_the_guard(string $locale): void
    {
        $inventory = AtlasTranslationUsageInventory::inventory(dirname(__DIR__, 3));
        $catalog = $this->catalog($locale);
        $key = 'breadcrumbs.atlas';

        self::assertArrayHasKey($key, $inventory);
        self::assertArrayHasKey($key, $catalog);

        unset($catalog[$key]);

        self::assertContains(
            'breadcrumbs.atlas (routes/breadcrumbs/admin.php, routes/breadcrumbs/application.php)',
            AtlasTranslationUsageInventory::missingKeys($inventory, $catalog),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'Polish mutation' => ['pl'];
        yield 'English mutation' => ['en'];
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        $root = dirname(__DIR__, 3);
        $json = json_decode((string) file_get_contents($root.'/lang/'.$locale.'.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        $catalog = [];

        foreach ($json as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $catalog[$key] = $value;
            }
        }

        foreach (['auth', 'pagination', 'passwords', 'validation'] as $name) {
            $values = require $root.'/lang/'.$locale.'/'.$name.'.php';
            self::assertIsArray($values);
            $this->flatten($catalog, $name, $values);
        }

        return $catalog;
    }

    /**
     * @param  array<string, string>  $catalog
     * @param  array<array-key, mixed>  $values
     */
    private function flatten(array &$catalog, string $prefix, array $values): void
    {
        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $fullKey = $prefix.'.'.$key;

            if (is_array($value)) {
                $this->flatten($catalog, $fullKey, $value);
            } elseif (is_string($value)) {
                $catalog[$fullKey] = $value;
            }
        }
    }
}
