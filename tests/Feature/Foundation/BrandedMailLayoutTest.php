<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Notifications\AccountLockedNotification;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class BrandedMailLayoutTest extends TestCase
{
    public function test_markdown_mail_uses_atlas_light_layout(): void
    {
        app()->setLocale('en');

        $html = (string) (new MailMessage)
            ->subject('Atlas email preview')
            ->greeting('MESSAGE CONTENT GREETING')
            ->line('MESSAGE CONTENT BODY')
            ->action('MESSAGE CONTENT ACTION', Config::string('app.url'))
            ->line('MESSAGE CONTENT SECOND LINE')
            ->render();

        $this->assertStringContainsString('brand-logo-cell', $html);
        $this->assertStringContainsString('/brand/atlas-mail-logo.png', $html);
        $this->assertStringContainsString('Debt collection operations', $html);
        $this->assertStringContainsString('#0f766e', $html);
        $this->assertStringContainsString('This message was generated automatically', $html);
        $this->assertStringContainsString('MESSAGE CONTENT BODY', $html);
        $this->assertStringNotContainsString('laravel.com/img/notification-logo', $html);
    }

    public function test_markdown_mail_layout_static_elements_follow_polish_locale(): void
    {
        app()->setLocale('pl');

        $html = (string) (new MailMessage)
            ->subject('Atlas email preview')
            ->greeting('MESSAGE CONTENT GREETING')
            ->line('MESSAGE CONTENT BODY')
            ->action('MESSAGE CONTENT ACTION', Config::string('app.url'))
            ->line('MESSAGE CONTENT SECOND LINE')
            ->render();

        $this->assertStringContainsString('System windykacyjny', $html);
        $this->assertStringContainsString('Ta wiadomość została wygenerowana automatycznie', $html);
        $this->assertStringContainsString('Z poważaniem', $html);
        $this->assertStringContainsString('Jeżeli masz problemy z kliknięciem przycisku', $html);
        $this->assertStringContainsString('MESSAGE CONTENT BODY', $html);
        $this->assertStringNotContainsString('Debt collection operations', $html);
        $this->assertStringNotContainsString('This message was generated automatically', $html);
    }

    public function test_atlas_notifications_render_through_branded_mail_layout(): void
    {
        app()->setLocale('en');

        $user = new User([
            'name' => 'Mail User',
            'email' => 'mail@example.test',
        ]);

        $renderedMessages = [
            (string) (new FirstPasswordSetupNotification('token', 'mail@example.test'))->toMail($user)->render(),
            (string) (new AccountLockedNotification(Carbon::parse('2026-07-15 12:00:00', 'Europe/Warsaw')))->toMail($user)->render(),
            (string) (new ResetPassword('token'))->toMail($user)->render(),
        ];

        foreach ($renderedMessages as $html) {
            $this->assertStringContainsString('brand-logo-cell', $html);
            $this->assertStringContainsString('/brand/atlas-mail-logo.png', $html);
            $this->assertStringContainsString('Debt collection operations', $html);
            $this->assertStringNotContainsString('laravel.com/img/notification-logo', $html);
        }
    }
}
