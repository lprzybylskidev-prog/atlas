<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class AdminModeController
{
    public function __construct(
        private AdministrativeSessionManager $adminMode,
        private ImpersonationManager $impersonation,
    ) {}

    public function enter(Request $request): RedirectResponse
    {
        $request->session()->put(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_ENTER);
        $request->session()->put('url.intended', route('admin.system-status'));

        return redirect()->route('password.confirm');
    }

    public function highRisk(Request $request): RedirectResponse
    {
        $request->session()->put(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_HIGH_RISK);
        $request->session()->put('url.intended', route('admin.system-status'));

        return redirect()->route('password.confirm');
    }

    public function exit(Request $request): RedirectResponse
    {
        $this->impersonation->stop($request, reason: 'admin_mode_exit');
        $this->adminMode->exit($request);

        return redirect()->route('dashboard')->with('flash.messages', [
            FlashMessage::success('flash.auth.admin_mode_ended'),
        ]);
    }
}
