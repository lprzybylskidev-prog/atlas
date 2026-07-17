<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

final readonly class ConfigureAtlasLogging
{
    public function __invoke(LoggerInterface $logger): void
    {
        if (method_exists($logger, 'getLogger')) {
            $logger = $logger->getLogger();
        }

        if (! $logger instanceof MonologLogger) {
            return;
        }

        $logger->pushProcessor(new RedactingLogProcessor);

        if (app()->environment('production')) {
            foreach ($logger->getHandlers() as $handler) {
                $this->setJsonFormatter($handler);
            }
        }
    }

    private function setJsonFormatter(HandlerInterface $handler): void
    {
        if (! method_exists($handler, 'setFormatter')) {
            return;
        }

        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
    }
}
