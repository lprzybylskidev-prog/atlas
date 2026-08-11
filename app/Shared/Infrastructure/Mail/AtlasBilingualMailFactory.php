<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\Contracts\MailLocaleSelector;
use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Application\Mail\DTOs\MailLocaleOrder;
use Illuminate\Notifications\Messages\MailMessage;

final readonly class AtlasBilingualMailFactory
{
    public function __construct(private MailLocaleSelector $locales) {}

    public function localeOrder(?int $userId = null, ?int $teamId = null): MailLocaleOrder
    {
        return $this->locales->select($userId, $teamId);
    }

    public function message(
        BilingualMailContent $content,
        ?int $userId = null,
        ?int $teamId = null,
    ): MailMessage {
        $order = $this->localeOrder($userId, $teamId);

        return (new MailMessage)
            ->subject($content->subjects[$order->effective])
            ->markdown('mail.atlas-bilingual', $this->viewData($content, $order));
    }

    /** @return array{sections: list<array{locale: string, heading: string, bodyLines: list<string>, actionLabel: string|null, actionUrl: string|null}>} */
    public function viewData(BilingualMailContent $content, MailLocaleOrder $order): array
    {
        $sections = [];

        foreach ($order->all() as $locale) {
            $sections[] = [
                'locale' => $locale,
                'heading' => $content->headings[$locale],
                'bodyLines' => $content->bodyLines[$locale],
                'actionLabel' => $content->actionLabels[$locale] ?? null,
                'actionUrl' => $content->actionUrl,
            ];
        }

        return ['sections' => $sections];
    }
}
