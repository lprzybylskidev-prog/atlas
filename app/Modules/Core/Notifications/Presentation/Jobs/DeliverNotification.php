<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Jobs;

use App\Modules\Core\Notifications\Infrastructure\Persistence\DatabaseNotificationStore;
use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMail;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $recipientId,
    ) {}

    public function handle(
        DatabaseNotificationStore $notifications,
        OperationalModuleGuard $modules,
        AtlasBilingualMailFactory $mail,
    ): void {
        $modules->ensureAllowed('notifications');

        $notifications->markDeliveredInApp($this->recipientId);

        if (! $notifications->emailRequested($this->recipientId)) {
            return;
        }

        $payloads = $notifications->emailPayloads($this->recipientId);

        if ($payloads === []) {
            $notifications->markEmailSkipped($this->recipientId);

            return;
        }

        foreach ($payloads as $payload) {
            $content = new BilingualMailContent(
                subjects: $payload['titles'],
                headings: $payload['titles'],
                bodyLines: [
                    'pl' => [$payload['bodies']['pl'] ?? trans('mail.notification.body_missing', [], 'pl')],
                    'en' => [$payload['bodies']['en'] ?? trans('mail.notification.body_missing', [], 'en')],
                ],
                actionLabels: $payload['deep_link_url'] === null ? null : [
                    'pl' => trans('mail.notification.action', [], 'pl'),
                    'en' => trans('mail.notification.action', [], 'en'),
                ],
                actionUrl: $payload['deep_link_url'],
            );
            $order = $mail->localeOrder($payload['user_id'], $payload['team_id']);

            Mail::to($payload['email'])->send(new AtlasBilingualMail($content, $order));
        }

        $notifications->markEmailDelivered($this->recipientId);
    }
}
