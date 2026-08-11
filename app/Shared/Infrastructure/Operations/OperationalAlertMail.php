<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Operations;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Infrastructure\Mail\AtlasBilingualMailFactory;
use Illuminate\Mail\Mailable;

final class OperationalAlertMail extends Mailable
{
    /**
     * @param  array<string, int|string>  $parameters
     */
    public function __construct(
        private readonly string $titleKey,
        private readonly string $bodyKey,
        private readonly array $parameters = [],
    ) {}

    public function build(): self
    {
        $content = BilingualMailContent::fromTranslationKeys(
            subjectKey: $this->titleKey,
            headingKey: $this->titleKey,
            bodyKeys: [$this->bodyKey, 'mail.operational_alert.guidance'],
            parameters: $this->parameters,
        );
        $mail = app(AtlasBilingualMailFactory::class);
        $order = $mail->localeOrder();

        return $this
            ->subject($content->subjects[$order->effective])
            ->markdown('mail.atlas-bilingual', $mail->viewData($content, $order));
    }
}
