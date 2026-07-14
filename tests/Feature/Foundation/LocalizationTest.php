<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Inertia\Testing\AssertableInertia;
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

    public function test_polish_and_english_json_language_catalogs_have_matching_keys(): void
    {
        $polish = json_decode((string) file_get_contents(lang_path('pl.json')), true);
        $english = json_decode((string) file_get_contents(lang_path('en.json')), true);

        self::assertIsArray($polish);
        self::assertIsArray($english);

        $polishKeys = array_keys($polish);
        $englishKeys = array_keys($english);

        sort($polishKeys);
        sort($englishKeys);

        self::assertSame($englishKeys, $polishKeys);
    }

    public function test_generated_fortify_password_message_has_polish_translation(): void
    {
        self::assertSame(
            'Podane hasło nie jest zgodne z aktualnym hasłem.',
            __('The provided password does not match your current password.'),
        );
    }

    public function test_locale_can_be_changed_for_the_browser_cookie(): void
    {
        $this->post('/locale', ['locale' => 'en'])
            ->assertRedirect()
            ->assertCookie('atlas_locale', 'en');

        $this->withCookie('atlas_locale', 'en')
            ->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('locale', 'en')
                ->where('supportedLocales', ['pl', 'en']));
    }

    public function test_locale_change_rejects_unsupported_locale(): void
    {
        $this->post('/locale', ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }
}
