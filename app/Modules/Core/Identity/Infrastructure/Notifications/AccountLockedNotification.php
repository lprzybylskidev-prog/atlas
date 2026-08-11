<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
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

        $id = data_get($notifiable, 'id');

        return app(AtlasBilingualMailFactory::class)->message(
            BilingualMailContent::fromTranslationKeys(
                subjectKey: 'mail.account_locked.subject',
                headingKey: 'mail.account_locked.heading',
                bodyKeys: ['mail.account_locked.body', 'mail.account_locked.expiry', 'mail.account_locked.guidance'],
                parameters: ['locked_until' => $this->lockedUntil->timezone($timezone)->format('Y-m-d H:i')],
            ),
            is_numeric($id) ? (int) $id : null,
        );
    }
}
