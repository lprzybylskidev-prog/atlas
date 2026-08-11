<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

final class UserEmailVerificationNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $id = data_get($notifiable, 'id');

        return app(AtlasBilingualMailFactory::class)->message(
            BilingualMailContent::fromTranslationKeys(
                subjectKey: 'mail.email_verification.subject',
                headingKey: 'mail.email_verification.heading',
                bodyKeys: ['mail.email_verification.body', 'mail.email_verification.guidance'],
                actionKey: 'mail.email_verification.action',
                actionUrl: $this->verificationUrl($notifiable),
            ),
            is_numeric($id) ? (int) $id : null,
        );
    }
}
