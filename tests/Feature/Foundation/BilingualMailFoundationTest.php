<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Notifications\AccountLockedNotification;
use App\Modules\Core\Identity\Infrastructure\Notifications\AtlasPasswordResetNotification;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserEmailVerificationNotification;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use App\Shared\Application\Mail\Contracts\MailLocaleSelector;
use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Application\Mail\DTOs\MailLocaleOrder;
use App\Shared\Infrastructure\Mail\AtlasBilingualMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use LogicException;
use Tests\TestCase;

final class BilingualMailFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_and_account_mail_paths_render_both_languages_in_effective_order(): void
    {
        $user = User::factory()->create(['email' => 'bilingual@example.test']);
        $settings = $this->app->make(SettingsStore::class);
        $messages = [
            new UserEmailVerificationNotification,
            new FirstPasswordSetupNotification('first-token', (string) $user->email, (int) $user->id),
            new AtlasPasswordResetNotification('reset-token'),
            new AccountLockedNotification(Carbon::parse('2026-08-10 12:00:00', 'Europe/Warsaw')),
        ];

        foreach (['pl', 'en'] as $effectiveLocale) {
            $settings->putUser((int) $user->id, UserSettingKey::UiLocale, $effectiveLocale);

            foreach ($messages as $notification) {
                $html = (string) $notification->toMail($user)->render();
                $polish = $this->expectedHeading($notification, 'pl');
                $english = $this->expectedHeading($notification, 'en');

                self::assertStringContainsString($polish, $html);
                self::assertStringContainsString($english, $html);
                self::assertSame(
                    $effectiveLocale === 'pl',
                    strpos($html, $polish) < strpos($html, $english),
                );
                self::assertStringContainsString('brand-logo-cell', $html);
                self::assertStringNotContainsString('first-token</p>', $html);
                self::assertStringNotContainsString('reset-token</p>', $html);
            }
        }
    }

    public function test_mail_locale_selector_uses_user_team_application_and_technical_fallback_order(): void
    {
        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Mail locale team']);
        $settings = $this->app->make(SettingsStore::class);
        $selector = $this->app->make(MailLocaleSelector::class);

        Config::set('app.locale', 'en');
        Config::set('app.fallback_locale', 'pl');
        self::assertSame(['en', 'pl'], $selector->select((int) $user->id, (int) $team->id)->all());

        $settings->putTeam((int) $team->id, TeamSettingKey::DefaultLocale, 'pl');
        self::assertSame(['pl', 'en'], $selector->select((int) $user->id, (int) $team->id)->all());

        $settings->putUser((int) $user->id, UserSettingKey::UiLocale, 'en');
        self::assertSame(['en', 'pl'], $selector->select((int) $user->id, (int) $team->id)->all());

        Config::set('app.locale', 'unsupported');
        Config::set('app.fallback_locale', 'pl');
        self::assertSame(['pl', 'en'], $selector->select()->all());
    }

    public function test_shared_bilingual_markdown_template_has_html_and_plain_text_parts(): void
    {
        $mail = new AtlasBilingualMail(
            BilingualMailContent::fromTranslationKeys(
                subjectKey: 'mail.password_reset.subject',
                headingKey: 'mail.password_reset.heading',
                bodyKeys: ['mail.password_reset.body'],
            ),
            new MailLocaleOrder('pl', 'en'),
        );

        $mail->assertSeeInOrderInHtml(['Odzyskiwanie hasła Atlas', 'Atlas password recovery']);
        $mail->assertSeeInOrderInText(['Odzyskiwanie hasła Atlas', 'Atlas password recovery']);
    }

    private function expectedHeading(object $notification, string $locale): string
    {
        $key = match ($notification::class) {
            UserEmailVerificationNotification::class => 'mail.email_verification.heading',
            FirstPasswordSetupNotification::class => 'mail.first_password.heading',
            AtlasPasswordResetNotification::class => 'mail.password_reset.heading',
            AccountLockedNotification::class => 'mail.account_locked.heading',
            default => throw new LogicException('Unexpected notification class in bilingual mail test.'),
        };

        return trans($key, [], $locale);
    }
}
