<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_polish_is_the_default_application_locale(): void
    {
        self::assertSame('pl', config('app.locale'));
        self::assertSame('en', config('app.fallback_locale'));
    }

    public function test_polish_and_english_language_catalogs_are_available(): void
    {
        self::assertFileExists(lang_path('pl.json'));
        self::assertFileExists(lang_path('en.json'));
        self::assertFileExists(lang_path('pl/validation.php'));
        self::assertFileExists(lang_path('en/validation.php'));
    }

    public function test_generated_fortify_password_message_has_polish_translation(): void
    {
        self::assertSame(
            'Podane hasło nie jest zgodne z aktualnym hasłem.',
            __('The provided password does not match your current password.'),
        );
    }
}
