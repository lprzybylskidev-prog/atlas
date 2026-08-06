<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Optional\TimeTracking\Application\BreakSessionCoordinator;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final readonly class StartBreakController
{
    public function __construct(private BreakSessionCoordinator $breaks) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        if ($userId === null) {
            abort(403);
        }

        try {
            $this->breaks->start($userId, new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')));
        } catch (RuntimeException) {
            return back()->with('flash.messages', [FlashMessage::error('flash.time_tracking.break_start_failed')]);
        }

        return redirect()->route(TimeTrackingPermissionCatalog::BREAK_SHOW);
    }

    private function userId(Request $request): ?int
    {
        $id = data_get($request->user(), 'id');

        return is_numeric($id) ? (int) $id : null;
    }
}
