<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

final class SettingsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_effective_locale_uses_user_team_guest_global_precedence(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);

        /** @var SettingsStore $store */
        $store = $this->app->make(SettingsStore::class);
        /** @var EffectiveSettings $settings */
        $settings = $this->app->make(EffectiveSettings::class);

        self::assertSame('en', $settings->locale(guestLocale: 'en'));

        $store->putGlobal(GlobalSettingKey::DefaultLocale, 'en');
        self::assertSame('en', $settings->locale());

        $store->putTeam((int) $team->id, TeamSettingKey::DefaultLocale, 'pl');
        self::assertSame('pl', $settings->locale(teamId: (int) $team->id, guestLocale: 'en'));

        $store->putUser((int) $user->id, UserSettingKey::UiLocale, 'en');
        self::assertSame('en', $settings->locale(userId: (int) $user->id, teamId: (int) $team->id));
    }

    public function test_effective_theme_uses_user_team_guest_global_precedence(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);

        /** @var SettingsStore $store */
        $store = $this->app->make(SettingsStore::class);
        /** @var EffectiveSettings $settings */
        $settings = $this->app->make(EffectiveSettings::class);

        self::assertSame('dark', $settings->theme(guestTheme: 'dark'));

        $store->putGlobal(GlobalSettingKey::DefaultTheme, 'dark');
        self::assertSame('dark', $settings->theme());

        $store->putTeam((int) $team->id, TeamSettingKey::DefaultTheme, 'light');
        self::assertSame('light', $settings->theme(teamId: (int) $team->id, guestTheme: 'dark'));

        $store->putUser((int) $user->id, UserSettingKey::Theme, 'dark');
        self::assertSame('dark', $settings->theme(userId: (int) $user->id, teamId: (int) $team->id));
    }

    public function test_setting_values_are_validated_by_typed_key(): void
    {
        /** @var SettingsStore $store */
        $store = $this->app->make(SettingsStore::class);

        $this->expectException(InvalidArgumentException::class);

        $store->putGlobal(GlobalSettingKey::DefaultLocale, 'de');
    }

    public function test_security_setting_changes_are_audited(): void
    {
        /** @var SettingsStore $store */
        $store = $this->app->make(SettingsStore::class);
        $actor = User::factory()->create();

        $store->putSecurity(SecuritySettingKey::MfaRequired, true, $actor->public_id, 'Hardening login policy.');

        self::assertSame(true, $store->getSecurity(SecuritySettingKey::MfaRequired));
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'settings',
            'action' => 'settings.security.updated',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_type' => 'security_setting',
            'target_public_id' => SecuritySettingKey::MfaRequired->value,
            'reason' => 'Hardening login policy.',
            'is_security' => true,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_SECURITY_EVENTS, [
            'category' => 'settings',
            'action' => 'settings.security.updated',
            'actor_public_id' => $actor->public_id,
        ]);
    }
}
