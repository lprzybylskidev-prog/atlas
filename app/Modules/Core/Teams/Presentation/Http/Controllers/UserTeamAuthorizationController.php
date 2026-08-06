<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamSessionLimitSettings;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class UserTeamAuthorizationController
{
    public function __construct(
        private UserTeamAuthorizationManager $authorization,
        private UserTeamSessionLimitSettings $sessionLimits,
        private UserBreakPolicySettings $breakPolicies,
    ) {}

    public function update(Request $request, string $user, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'role_names' => ['array'],
            'role_names.*' => ['string'],
            'direct_permission_names' => ['array'],
            'direct_permission_names.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
            'inactivity_timeout_minutes' => ['nullable', 'integer', 'min:1'],
            'session_max_lifetime_minutes' => ['nullable', 'integer', 'min:1'],
            'break_daily_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'break_maximum_single_minutes' => ['nullable', 'integer', 'min:1'],
        ], [], [
            'inactivity_timeout_minutes' => __('validation.attributes.inactivity_timeout_minutes'),
            'session_max_lifetime_minutes' => __('validation.attributes.session_max_lifetime_minutes'),
            'break_daily_limit_minutes' => __('validation.attributes.time_tracking_break_daily_limit_minutes'),
            'break_maximum_single_minutes' => __('validation.attributes.time_tracking_break_maximum_single_minutes'),
        ]);
        $validated = is_array($validated) ? $validated : [];
        $inactivityTimeoutMinutes = $this->nullableIntValue($validated, 'inactivity_timeout_minutes');
        $sessionMaxLifetimeMinutes = $this->nullableIntValue($validated, 'session_max_lifetime_minutes');

        $this->validateSessionLimits($team, $inactivityTimeoutMinutes, $sessionMaxLifetimeMinutes);

        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            $this->authorization->replaceAssignmentsForUserTeam(
                actorPublicId: $actorPublicId,
                userPublicId: $user,
                teamPublicId: $team,
                roleNames: $this->stringList($validated['role_names'] ?? []),
                directPermissionNames: $this->stringList($validated['direct_permission_names'] ?? []),
                reason: is_string($validated['reason'] ?? null) ? $validated['reason'] : null,
            );
            $this->sessionLimits->setUserTeamOverrides($user, $team, $inactivityTimeoutMinutes, $sessionMaxLifetimeMinutes);
            $this->breakPolicies->setUserTeamOverrides(
                $user,
                $team,
                $this->nullableIntValue($validated, 'break_daily_limit_minutes'),
                $this->nullableIntValue($validated, 'break_maximum_single_minutes'),
            );
        }

        return redirect()->route('admin.users.edit', ['user' => $user])->with('flash.messages', [
            FlashMessage::success('flash.teams.authorization_updated'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }

    /**
     * @param  array<mixed>  $values
     */
    private function nullableIntValue(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function validateSessionLimits(string $teamPublicId, ?int $inactivityTimeoutMinutes, ?int $sessionMaxLifetimeMinutes): void
    {
        $teamLimits = $this->sessionLimits->resolvedForTeam($teamPublicId);
        $effectiveInactivity = $inactivityTimeoutMinutes ?? $teamLimits['inactivityTimeoutMinutes'];
        $effectiveMaximum = $sessionMaxLifetimeMinutes ?? $teamLimits['sessionMaxLifetimeMinutes'];

        if ($effectiveInactivity <= $effectiveMaximum) {
            return;
        }

        throw ValidationException::withMessages([
            'inactivity_timeout_minutes' => __('validation.custom.session_limits.inactivity_not_greater_than_maximum'),
        ]);
    }
}
