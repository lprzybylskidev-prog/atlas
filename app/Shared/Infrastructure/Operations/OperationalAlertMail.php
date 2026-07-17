<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use Illuminate\Mail\Mailable;

final class OperationalAlertMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly string $alertTitle,
        private readonly string $alertBody,
        private readonly array $payload,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('[Atlas alert] '.$this->alertTitle)
            ->text('mail.operational-alert', [
                'body' => $this->alertBody,
                'payload' => json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
    }
}
