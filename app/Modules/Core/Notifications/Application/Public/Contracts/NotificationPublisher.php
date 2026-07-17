<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;

interface NotificationPublisher
{
    public function publish(CreateNotification $notification): string;
}
