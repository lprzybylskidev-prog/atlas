<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_polish_and_english_php_language_catalogs_have_matching_keys(): void
    {
        $catalogs = ['auth', 'pagination', 'passwords', 'validation'];

        foreach ($catalogs as $catalog) {
            $polish = require lang_path(sprintf('pl/%s.php', $catalog));
            $english = require lang_path(sprintf('en/%s.php', $catalog));

            self::assertIsArray($polish);
            self::assertIsArray($english);

            self::assertSame(
                $this->flattenTranslationKeys($english),
                $this->flattenTranslationKeys($polish),
                sprintf('The [%s] language catalog must keep PL/EN key parity.', $catalog),
            );
        }
    }

    public function test_current_password_mismatch_has_stable_polish_translation(): void
    {
        self::assertSame(
            'Podane hasło nie jest zgodne z aktualnym hasłem.',
            __('auth.password_current_mismatch'),
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

    public function test_authenticated_locale_change_is_persisted_as_user_setting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect()
            ->assertCookie('atlas_locale', 'en');

        self::assertDatabaseHas('settings_user_values', [
            'user_id' => $user->id,
            'key' => UserSettingKey::UiLocale->value,
            'value' => '"en"',
        ]);

        $this->actingAs($user)
            ->withCookie('atlas_locale', 'pl')
            ->get('/login')
            ->assertRedirect('/');

        $this->actingAs($user)
            ->withCookie('atlas_locale', 'pl')
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));

        self::assertSame('"en"', DB::table('settings_user_values')->where('user_id', $user->id)->value('value'));
    }

    public function test_authenticated_theme_change_is_persisted_as_user_setting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/theme', ['theme' => 'dark'])
            ->assertRedirect()
            ->assertCookie('atlas_theme', 'dark');

        self::assertDatabaseHas('settings_user_values', [
            'user_id' => $user->id,
            'key' => UserSettingKey::Theme->value,
            'value' => '"dark"',
        ]);

        $this->actingAs($user)
            ->withCookie('atlas_theme', 'light')
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('preferences.theme', 'dark'));
    }

    public function test_guest_theme_change_uses_temporary_cookie_preference(): void
    {
        $this->post('/theme', ['theme' => 'dark'])
            ->assertRedirect()
            ->assertCookie('atlas_theme', 'dark');

        $this->withCookie('atlas_theme', 'dark')
            ->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('preferences.theme', 'dark'));
    }

    public function test_locale_change_rejects_unsupported_locale(): void
    {
        $this->post('/locale', ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_theme_change_rejects_unsupported_theme(): void
    {
        $this->post('/theme', ['theme' => 'sepia'])
            ->assertSessionHasErrors('theme');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function flattenTranslationKeys(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                $keys[] = $prefix === '' ? '__non_string_key__' : sprintf('%s.__non_string_key__', $prefix);

                continue;
            }

            $fullKey = $prefix === '' ? $key : sprintf('%s.%s', $prefix, $key);

            if (is_array($value)) {
                array_push($keys, ...$this->flattenTranslationKeys($value, $fullKey));

                continue;
            }

            $keys[] = $fullKey;
        }

        sort($keys);

        return $keys;
    }
}
