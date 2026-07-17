<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use Sentry\Event;
use Sentry\UserDataBag;

final class SanitizedSentryEventProcessor
{
    public static function handle(Event $event): Event
    {
        $redactor = new SensitiveDataRedactor;

        $event->setRequest($redactor->redactStringKeyedArray($event->getRequest()));
        $event->setExtra($redactor->redactStringKeyedArray($event->getExtra()));

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext($name, $redactor->redactStringKeyedArray($context));
        }

        $user = $event->getUser();

        if ($user instanceof UserDataBag) {
            $event->setUser(new UserDataBag($user->getId()));
        }

        return $event;
    }
}
