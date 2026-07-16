<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

final class UserEmailVerificationNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your Atlas email address')
            ->line('Your Atlas email address needs to be verified before email-sensitive account actions are trusted.')
            ->action('Verify email address', $url)
            ->line('This link verifies your email address only. It does not change your password.');
    }
}
