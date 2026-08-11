<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Application\Mail\DTOs\BilingualMailContent;
use App\Shared\Application\Mail\DTOs\MailLocaleOrder;
use Illuminate\Mail\Mailable;

final class AtlasBilingualMail extends Mailable
{
    public function __construct(
        private readonly BilingualMailContent $mailContent,
        private readonly MailLocaleOrder $localeOrder,
    ) {}

    public function build(): self
    {
        $factory = app(AtlasBilingualMailFactory::class);

        return $this
            ->subject($this->mailContent->subjects[$this->localeOrder->effective])
            ->markdown('mail.atlas-bilingual', $factory->viewData($this->mailContent, $this->localeOrder));
    }
}
