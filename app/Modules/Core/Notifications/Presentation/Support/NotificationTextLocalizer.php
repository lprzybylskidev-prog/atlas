<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Support;

use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;

final class NotificationTextLocalizer
{
    public function title(NotificationSummary $notification): string
    {
        return $this->line($notification, 'title', $notification->title) ?? $notification->title;
    }

    public function body(NotificationSummary $notification): ?string
    {
        return $this->line($notification, 'body', $notification->body);
    }

    private function line(NotificationSummary $notification, string $field, ?string $fallback): ?string
    {
        $key = $notification->data[$field.'_key'] ?? null;

        if (! is_string($key) || ! str_starts_with($key, 'notifications.')) {
            return $fallback;
        }

        return __($key, $this->parameters($notification->data));
    }

    /**
     * @param  array<string, scalar|null>  $data
     * @return array<string, scalar|null>
     */
    private function parameters(array $data): array
    {
        $parameters = [];

        foreach ($data as $key => $value) {
            if (str_ends_with($key, '_key')) {
                continue;
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }
}
