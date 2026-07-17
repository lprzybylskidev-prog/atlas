<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class RedactingLogProcessor implements ProcessorInterface
{
    public function __construct(
        private SensitiveDataRedactor $redactor = new SensitiveDataRedactor,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactor->redactArray($record->context),
            extra: $this->redactor->redactArray($record->extra),
        );
    }
}
