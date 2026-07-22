<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmPassword;

final readonly class ConfirmPasswordController
{
    public function __construct(
        private StatefulGuard $guard,
        private AdministrativeSessionManager $adminMode,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $pending = $request->hasSession() ? $request->session()->get(AdministrativeSessionManager::PENDING_REAUTHENTICATION) : null;

        return Inertia::render('Auth/ConfirmPassword', [
            'mfaRequired' => $user instanceof User && $this->adminMode->requiresMfa($request, $user),
            'context' => is_string($pending) ? $pending : 'default',
        ]);
    }

    public function store(Request $request, ConfirmPassword $confirmPassword): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $password = is_string($validated['password'] ?? null) ? $validated['password'] : '';
        $mfaCode = is_string($validated['mfa_code'] ?? null) ? $validated['mfa_code'] : null;
        $user = $request->user();

        if (! $user instanceof User || ! $confirmPassword($this->guard, $user, $password)) {
            throw ValidationException::withMessages([
                'password' => __('The provided password was incorrect.'),
            ]);
        }

        if ($this->adminMode->requiresMfa($request, $user) && ! $this->adminMode->validMfa($user, $mfaCode)) {
            throw ValidationException::withMessages([
                'mfa_code' => 'The provided MFA code was invalid.',
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());
        $pending = $request->session()->get(AdministrativeSessionManager::PENDING_REAUTHENTICATION);

        if ($pending === AdministrativeSessionManager::PENDING_ENTER) {
            $request->session()->forget(AdministrativeSessionManager::PENDING_REAUTHENTICATION);

            if (! $this->adminMode->enterConfirmed($request, $user)) {
                throw ValidationException::withMessages([
                    'password' => 'Administrative mode reauthentication failed.',
                ]);
            }

            return redirect()->intended(route('admin.system-status'))->with('flash.messages', [
                FlashMessage::success('flash.auth.admin_mode_active'),
            ]);
        }

        if ($pending === AdministrativeSessionManager::PENDING_HIGH_RISK) {
            $request->session()->forget(AdministrativeSessionManager::PENDING_REAUTHENTICATION);

            if (! $this->adminMode->confirmHighRiskConfirmed($request, $user)) {
                throw ValidationException::withMessages([
                    'password' => 'High-risk reauthentication failed.',
                ]);
            }

            return redirect()->intended(route('admin.system-status'))->with('flash.messages', [
                FlashMessage::success('flash.auth.high_risk_fresh'),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
