<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

final readonly class OperationalAlertDispatcher
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function send(string $type, string $title, string $body, string $severity = 'warning', array $context = []): bool
    {
        if (! config()->boolean('atlas.operations.alerts.enabled', false)) {
            return false;
        }

        $fingerprint = hash('sha256', $type.'|'.$title.'|'.$body);
        $dedupeKey = 'atlas:alerts:dedupe:'.$fingerprint;
        $throttleKey = 'atlas:alerts:throttle:'.$type;
        $dedupeSeconds = max(60, config()->integer('atlas.operations.alerts.dedupe_seconds', 900));
        $throttleSeconds = max(60, config()->integer('atlas.operations.alerts.throttle_seconds', 300));

        if (! Cache::add($dedupeKey, true, $dedupeSeconds)) {
            return false;
        }

        if (! Cache::add($throttleKey, true, $throttleSeconds)) {
            return false;
        }

        $payload = [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'environment' => config()->string('app.env'),
            'release_id' => config()->string('atlas.release.id'),
            'context' => $context,
        ];

        $this->sendEmail($title, $body, $payload);
        $this->sendWebhook($payload);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendEmail(string $title, string $body, array $payload): void
    {
        $recipients = config('atlas.operations.alerts.email_to');

        if (! is_array($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient) || $recipient === '') {
                continue;
            }

            Mail::to($recipient)->send(new OperationalAlertMail($title, $body, $payload));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload): void
    {
        $webhookUrl = config('atlas.operations.alerts.webhook_url');

        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        Http::timeout(5)->post($webhookUrl, $payload)->throw();
    }
}
