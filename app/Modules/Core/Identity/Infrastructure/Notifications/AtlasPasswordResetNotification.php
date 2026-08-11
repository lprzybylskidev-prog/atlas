<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

final class AtlasPasswordResetNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof CanResetPassword
            ? $notifiable->getEmailForPasswordReset()
            : data_get($notifiable, 'email');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('Password reset notification recipient must expose an email address.');
        }

        $url = url(route('password.reset', ['token' => $this->token, 'email' => $email], false));
        $broker = config('auth.defaults.passwords');
        $broker = is_string($broker) && $broker !== '' ? $broker : 'users';
        $expiry = config("auth.passwords.{$broker}.expire");
        $expiry = is_int($expiry) ? $expiry : 15;

        return app(AtlasBilingualMailFactory::class)->message(
            BilingualMailContent::fromTranslationKeys(
                subjectKey: 'mail.password_reset.subject',
                headingKey: 'mail.password_reset.heading',
                bodyKeys: ['mail.password_reset.body', 'mail.password_reset.expiry'],
                actionKey: 'mail.password_reset.action',
                actionUrl: $url,
                parameters: ['minutes' => $expiry],
            ),
            $this->internalUserId($notifiable),
        );
    }

    private function internalUserId(object $notifiable): ?int
    {
        $id = data_get($notifiable, 'id');

        return is_numeric($id) ? (int) $id : null;
    }
}
