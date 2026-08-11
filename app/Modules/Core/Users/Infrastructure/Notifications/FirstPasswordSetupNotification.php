<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Infrastructure\Notifications;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class FirstPasswordSetupNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $email,
        private readonly ?int $userId = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $passwordBroker = config('auth.defaults.passwords');
        $passwordBroker = is_string($passwordBroker) && $passwordBroker !== '' ? $passwordBroker : 'users';
        $expiryMinutes = config("auth.passwords.{$passwordBroker}.expire");
        $expiryMinutes = is_int($expiryMinutes) ? $expiryMinutes : 15;

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->email,
        ], false));

        return app(AtlasBilingualMailFactory::class)->message(
            BilingualMailContent::fromTranslationKeys(
                subjectKey: 'mail.first_password.subject',
                headingKey: 'mail.first_password.heading',
                bodyKeys: ['mail.first_password.body', 'mail.first_password.expiry', 'mail.first_password.guidance'],
                actionKey: 'mail.first_password.action',
                actionUrl: $url,
                parameters: ['minutes' => $expiryMinutes],
            ),
            $this->userId,
        );
    }
}
