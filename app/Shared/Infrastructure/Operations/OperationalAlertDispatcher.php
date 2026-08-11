<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

final readonly class OperationalAlertDispatcher
{
    /**
     * @param  array<string, int|string>  $parameters
     * @param  array<string, scalar|null>  $context
     */
    public function send(
        string $type,
        string $titleKey,
        string $bodyKey,
        string $severity = 'warning',
        array $parameters = [],
        array $context = [],
    ): bool {
        $this->assertMailTranslationKey($titleKey);
        $this->assertMailTranslationKey($bodyKey);

        if (! config()->boolean('atlas.operations.alerts.enabled', false)) {
            return false;
        }

        $fingerprint = hash('sha256', $type.'|'.$titleKey.'|'.$bodyKey.'|'.json_encode($parameters));
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

        $title = trans($titleKey, $parameters, 'en');
        $body = trans($bodyKey, $parameters, 'en');
        $payload = [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'severity' => $severity,
            'environment' => config()->string('app.env'),
            'release_id' => config()->string('atlas.release.id'),
            'context' => $context,
        ];

        $this->sendEmail($titleKey, $bodyKey, $parameters);
        $this->sendWebhook($payload);

        return true;
    }

    /**
     * @param  array<string, int|string>  $parameters
     */
    private function sendEmail(string $titleKey, string $bodyKey, array $parameters): void
    {
        $recipients = config('atlas.operations.alerts.email_to');

        if (! is_array($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient) || $recipient === '') {
                continue;
            }

            Mail::to($recipient)->send(new OperationalAlertMail($titleKey, $bodyKey, $parameters));
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

    private function assertMailTranslationKey(string $key): void
    {
        if (! str_starts_with($key, 'mail.operational_alert.') || ! Lang::has($key, 'pl') || ! Lang::has($key, 'en')) {
            throw new InvalidArgumentException(sprintf('Operational alert mail key [%s] must exist in Polish and English.', $key));
        }
    }
}
