<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class FirstPasswordSetupNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $email,
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

        return (new MailMessage)
            ->subject('Set your Atlas password')
            ->line('Your Atlas account has been created.')
            ->line('Use the button below to set your first password and verify your email address.')
            ->action('Set password', $url)
            ->line(sprintf(
                'This one-time link expires in %d minutes.',
                $expiryMinutes,
            ))
            ->line('Atlas never sends generated passwords.');
    }
}
