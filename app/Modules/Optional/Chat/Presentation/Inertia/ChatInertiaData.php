<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Inertia;

use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;

final class ChatInertiaData implements InertiaSharedDataContributor
{
    public function key(): string
    {
        return 'optional.chat.shared';
    }

    public function data(Request $request): array
    {
        return ['chat' => ['browserNotificationsEnabled' => (bool) config('chat.browser_notifications_enabled', true)]];
    }
}
