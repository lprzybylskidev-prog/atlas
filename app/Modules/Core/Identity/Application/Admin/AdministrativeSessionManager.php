<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Admin;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Public\Contracts\AdministrativeSecuritySettings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final readonly class AdministrativeSessionManager
{
    private const ADMIN_MODE_ENTER_PERMISSION = 'admin-mode.enter';

    public const PENDING_REAUTHENTICATION = 'atlas_admin_pending_reauthentication';

    public const PENDING_ENTER = 'enter';

    public const PENDING_HIGH_RISK = 'high_risk';

    public const ENTERED_AT = 'atlas_admin_mode_entered_at';

    public const LAST_ACTIVITY_AT = 'atlas_admin_mode_last_activity_at';

    public const HIGH_RISK_CONFIRMED_AT = 'atlas_admin_high_risk_confirmed_at';

    public function __construct(
        private AdministrativeSecuritySettings $settings,
        private EffectivePermissionChecker $permissions,
        private AuditRecorder $audit,
        private TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    public function active(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return $this->expiredReason($request->session()) === null;
    }

    public function enter(Request $request, User $user, string $password, ?string $mfaCode = null): bool
    {
        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($teamPublicId) || ! $this->canEnter((string) $user->public_id, $teamPublicId)) {
            $this->record($request, 'admin_mode.enter', 'rejected', (string) $user->public_id, $teamPublicId);

            return false;
        }

        if (! Hash::check($password, (string) $user->password)) {
            $this->record($request, 'admin_mode.enter', 'rejected', (string) $user->public_id, $teamPublicId);

            return false;
        }

        if ($this->requiresMfaForTeam($user, $teamPublicId) && ! $this->validMfa($user, $mfaCode)) {
            $this->record($request, 'admin_mode.enter', 'rejected', (string) $user->public_id, $teamPublicId);

            return false;
        }

        return $this->enterConfirmed($request, $user);
    }

    public function enterConfirmed(Request $request, User $user): bool
    {
        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($teamPublicId) || ! $this->canEnter((string) $user->public_id, $teamPublicId)) {
            $this->record($request, 'admin_mode.enter', 'rejected', (string) $user->public_id, is_string($teamPublicId) ? $teamPublicId : null);

            return false;
        }

        $this->activate($request);
        $this->record($request, 'admin_mode.enter', 'succeeded', (string) $user->public_id, $teamPublicId);

        return true;
    }

    public function touch(Request $request): void
    {
        if ($request->hasSession() && $this->active($request)) {
            $request->session()->put(self::LAST_ACTIVITY_AT, Carbon::now()->toIso8601String());
        }
    }

    public function exit(Request $request, string $reason = 'manual'): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            self::ENTERED_AT,
            self::LAST_ACTIVITY_AT,
            self::HIGH_RISK_CONFIRMED_AT,
            'auth.password_confirmed_at',
        ]);
        $this->record($request, 'admin_mode.exit', 'succeeded', data_get($request->user(), 'public_id'), $request->session()->get('active_team_public_id'), $reason);
    }

    public function confirmHighRisk(Request $request, User $user, string $password, ?string $mfaCode = null): bool
    {
        if (! $this->active($request) || ! Hash::check($password, (string) $user->password)) {
            $this->record($request, 'admin_mode.high_risk_confirm', 'rejected', (string) $user->public_id, $request->session()->get('active_team_public_id'));

            return false;
        }

        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($teamPublicId) || ($this->requiresMfaForTeam($user, $teamPublicId) && ! $this->validMfa($user, $mfaCode))) {
            $this->record($request, 'admin_mode.high_risk_confirm', 'rejected', (string) $user->public_id, is_string($teamPublicId) ? $teamPublicId : null);

            return false;
        }

        return $this->confirmHighRiskConfirmed($request, $user);
    }

    public function confirmHighRiskConfirmed(Request $request, User $user): bool
    {
        if (! $this->active($request)) {
            $this->record($request, 'admin_mode.high_risk_confirm', 'rejected', (string) $user->public_id, $request->session()->get('active_team_public_id'));

            return false;
        }

        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($teamPublicId)) {
            $this->record($request, 'admin_mode.high_risk_confirm', 'rejected', (string) $user->public_id, null);

            return false;
        }

        $request->session()->put(self::HIGH_RISK_CONFIRMED_AT, Carbon::now()->toIso8601String());
        $this->record($request, 'admin_mode.high_risk_confirm', 'succeeded', (string) $user->public_id, $teamPublicId);

        return true;
    }

    public function highRiskFresh(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $confirmedAt = $this->timestamp($request->session()->get(self::HIGH_RISK_CONFIRMED_AT));

        return $confirmedAt !== null
            && $confirmedAt->copy()->addMinutes($this->settings->adminHighRiskTimeoutMinutes())->gt(Carbon::now());
    }

    public function expiredReason(Session $session): ?string
    {
        $enteredAt = $this->timestamp($session->get(self::ENTERED_AT));
        $lastActivityAt = $this->timestamp($session->get(self::LAST_ACTIVITY_AT));

        if ($enteredAt === null || $lastActivityAt === null) {
            return 'missing';
        }

        $now = Carbon::now();

        if ($enteredAt->copy()->addMinutes($this->settings->adminModeAbsoluteLifetimeMinutes())->lte($now)) {
            return 'absolute_lifetime';
        }

        if ($lastActivityAt->copy()->addMinutes($this->settings->adminModeInactivityTimeoutMinutes())->lte($now)) {
            return 'inactivity';
        }

        return null;
    }

    private function canEnter(string $userPublicId, string $teamPublicId): bool
    {
        return $this->permissions->check(new EffectivePermissionRequest(
            userPublicId: $userPublicId,
            permission: self::ADMIN_MODE_ENTER_PERMISSION,
            teamPublicId: $teamPublicId,
        ))->allowed;
    }

    public function requiresMfa(Request $request, User $user): bool
    {
        $teamPublicId = $request->session()->get('active_team_public_id');

        return is_string($teamPublicId) && $this->requiresMfaForTeam($user, $teamPublicId);
    }

    public function validMfa(User $user, ?string $code): bool
    {
        if ($user->two_factor_confirmed_at === null) {
            return false;
        }

        if (! is_string($code) || trim($code) === '' || ! is_string($user->two_factor_secret)) {
            return false;
        }

        $decryptedTotpKey = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return is_string($decryptedTotpKey) && $this->twoFactor->verify($decryptedTotpKey, $code);
    }

    private function requiresMfaForTeam(User $user, string $teamPublicId): bool
    {
        if ($user->two_factor_confirmed_at !== null) {
            return true;
        }

        return $this->settings->mfaRequired();
    }

    private function activate(Request $request): void
    {
        $now = Carbon::now()->toIso8601String();
        $request->session()->put(self::ENTERED_AT, $now);
        $request->session()->put(self::LAST_ACTIVITY_AT, $now);
        $request->session()->put('auth.password_confirmed_at', Carbon::now()->unix());
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private function record(Request $request, string $action, string $result, mixed $actorPublicId, mixed $teamPublicId, ?string $reason = null): void
    {
        $this->audit->record(new AuditEvent(
            module: 'identity',
            action: $action,
            result: $result,
            source: 'ui',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            reason: $reason,
            security: true,
            securityCategory: 'administrative_mode',
        ));
    }
}
