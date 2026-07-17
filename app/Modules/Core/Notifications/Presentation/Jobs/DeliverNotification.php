<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Jobs;

use App\Modules\Core\Notifications\Infrastructure\Persistence\DatabaseNotificationStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
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

    public function handle(DatabaseNotificationStore $notifications): void
    {
        $notifications->markDeliveredInApp($this->recipientId);

        if (! $notifications->emailRequested($this->recipientId)) {
            return;
        }

        $payload = $notifications->emailPayload($this->recipientId);

        if ($payload === null) {
            $notifications->markEmailSkipped($this->recipientId);

            return;
        }

        Mail::raw($payload['body'] ?? $payload['title'], function (Message $message) use ($payload): void {
            $message->to($payload['email'])->subject($payload['title']);
        });

        $notifications->markEmailDelivered($this->recipientId);
    }
}
