<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\MfaRequirementChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\UserPasswordExpiration;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionLimitResolver;
use App\Modules\Core\Identity\Application\Public\DTOs\MfaRequirementContext;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationTypeDirectory;
use App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;
use App\Shared\Application\Files\Contracts\FileAvailability;
use App\Shared\Application\TimeTracking\Contracts\UserBreakPolicySettings;
use App\Shared\Application\TimeTracking\Permissions\TimeTrackingPermissionNames;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserProfileController
{
    public function __invoke(
        Request $request,
        UserPasswordExpiration $passwords,
        UserSessionLimitResolver $sessions,
        NotificationTypeDirectory $notificationTypes,
        NotificationEmailPreferenceManager $emails,
        EffectivePermissionChecker $permissions,
        UserBreakPolicySettings $breakPolicies,
        FileAvailability $files,
        MfaRequirementChecker $mfaRequirements,
    ): Response {
        $user = $request->user();
        $userId = $this->intValue(data_get($user, 'id'));
        $userPublicId = $this->stringValue(data_get($user, 'public_id'));
        $email = $this->stringValue(data_get($user, 'email'));
        $emailVerifiedAt = data_get($user, 'email_verified_at');

        return Inertia::render('User/Panel', [
            'profile' => [
                'name' => $this->stringValue(data_get($user, 'name')),
                'email' => $email,
                'password' => [
                    'changedAt' => $this->dateTimeString(data_get($user, 'password_changed_at')),
                    'expiresAt' => $passwords->expiresAtForUserId($userId)?->toISOString(),
                    'expiresAfterDays' => $passwords->expiresAfterDays(),
                ],
                'session' => [
                    'inactivityTimeoutMinutes' => $sessions->limitsForUserId($userId, $this->activeTeamPublicId($request))['inactivity'],
                ],
                'timeTracking' => [
                    'breakDailyLimitMinutes' => $this->breakDailyLimitMinutes($request, $userPublicId, $breakPolicies, $permissions),
                ],
                'mfa' => [
                    'enabled' => data_get($user, 'two_factor_confirmed_at') !== null,
                    'pendingConfirmation' => data_get($user, 'two_factor_secret') !== null && data_get($user, 'two_factor_confirmed_at') === null,
                    'confirmedAt' => $this->dateTimeString(data_get($user, 'two_factor_confirmed_at')),
                    'required' => $mfaRequirements->isRequired(new MfaRequirementContext(
                        userPublicId: $userPublicId,
                        teamPublicId: $this->activeTeamPublicId($request),
                        operation: 'users.profile',
                        permissions: ['users.profile'],
                    )),
                ],
                'avatar' => [
                    'color' => $this->stringValue(data_get($user, 'avatar_color')),
                    'imageUrl' => $this->avatarImageUrl($files, data_get($user, 'avatar_image_file_public_id')),
                ],
                'notificationEmails' => $emails->addressesForUser(
                    $userId,
                    $email,
                    $emailVerifiedAt instanceof DateTimeInterface ? $emailVerifiedAt : null,
                    $this->activeTeamPublicId($request),
                ),
                'notificationTypes' => array_map(
                    static fn (array $type): array => [
                        'type' => $type['type'],
                        'labelKey' => $type['labelKey'],
                        'descriptionKey' => $type['descriptionKey'],
                        'bodyPreviewKey' => $type['bodyPreviewKey'],
                        'bodyPreviewParams' => $type['bodyPreviewParams'],
                    ],
                    $this->visibleNotificationTypes($request, $userPublicId, $notificationTypes, $permissions),
                ),
            ],
        ]);
    }

    private function breakDailyLimitMinutes(
        Request $request,
        string $userPublicId,
        UserBreakPolicySettings $breakPolicies,
        EffectivePermissionChecker $permissions,
    ): ?int {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if ($userPublicId === '' || ! is_string($teamPublicId) || $teamPublicId === '') {
            return null;
        }

        if (! $permissions->check(new EffectivePermissionRequest($userPublicId, TimeTrackingPermissionNames::USER_REPORT, $teamPublicId))->allowed) {
            return null;
        }

        if (! $breakPolicies->isTrackedForUserTeam($userPublicId, $teamPublicId)) {
            return null;
        }

        return $breakPolicies->resolvedForUserTeam($userPublicId, $teamPublicId)['dailyLimitMinutes'];
    }

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) && $teamPublicId !== '' ? $teamPublicId : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleNotificationTypes(
        Request $request,
        string $userPublicId,
        NotificationTypeDirectory $notificationTypes,
        EffectivePermissionChecker $permissions,
    ): array {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if ($userPublicId === '' || ! is_string($teamPublicId)) {
            return [];
        }

        return array_values(array_filter(
            $notificationTypes->types(),
            fn (array $type): bool => $this->canReceiveNotificationType($permissions, $userPublicId, $teamPublicId, $type),
        ));
    }

    /**
     * @param  array{permissionNames: list<string>}  $type
     */
    private function canReceiveNotificationType(
        EffectivePermissionChecker $permissions,
        string $userPublicId,
        string $teamPublicId,
        array $type,
    ): bool {
        foreach ($type['permissionNames'] as $permissionName) {
            if ($permissions->check(new EffectivePermissionRequest($userPublicId, $permissionName, $teamPublicId))->allowed) {
                return true;
            }
        }

        return false;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function dateTimeString(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }

    private function avatarImageUrl(FileAvailability $files, mixed $filePublicId): ?string
    {
        if (! is_string($filePublicId) || $filePublicId === '') {
            return null;
        }

        return $files->clean($filePublicId) ? route('users.profile.avatar-image', absolute: false) : null;
    }
}
