<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

final class AccountLockedNotification extends Notification
{
    public function __construct(
        private readonly Carbon $lockedUntil,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'Europe/Warsaw';

        return (new MailMessage)
            ->subject('Atlas account temporarily locked')
            ->line('Your Atlas account was temporarily locked after repeated failed login attempts.')
            ->line(sprintf('The lock expires at %s.', $this->lockedUntil->timezone($timezone)->format('Y-m-d H:i')))
            ->line('If this was not you, contact an administrator.');
    }
}
