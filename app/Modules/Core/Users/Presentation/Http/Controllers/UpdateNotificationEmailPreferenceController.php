<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationTypeDirectory;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class UpdateNotificationEmailPreferenceController
{
    public function __construct(
        private NotificationTypeDirectory $catalog,
        private NotificationEmailPreferenceManager $emails,
        private EffectivePermissionChecker $permissions,
    ) {}

    public function __invoke(Request $request, string $email): RedirectResponse
    {
        $user = $request->user();
        $visibleTypes = $this->visibleTypeNames($request, $user);

        $request->validate([
            'enabled_types' => ['array'],
            'enabled_types.*' => ['string', Rule::in($visibleTypes)],
        ]);
        $rawEnabledTypes = $request->input('enabled_types', []);
        $enabledTypes = array_values(array_filter(
            is_array($rawEnabledTypes) ? $rawEnabledTypes : [],
            static fn (mixed $type): bool => is_string($type),
        ));

        $sessionTeamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamPublicId = is_string($sessionTeamPublicId) ? $sessionTeamPublicId : null;

        $this->emails->updatePreferencesForUser($this->intValue(data_get($user, 'id')), $email, $enabledTypes, $visibleTypes, $teamPublicId);

        return back()->with('flash.messages', [
            FlashMessage::success('flash.user_profile.notification_preferences_updated'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function visibleTypeNames(Request $request, mixed $user): array
    {
        $userPublicId = $this->stringValue(data_get($user, 'public_id'));
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if ($userPublicId === '' || ! is_string($teamPublicId)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $type): string => $type['type'],
            array_filter(
                $this->catalog->types(),
                fn (array $type): bool => $this->userCanReceiveNotificationType($userPublicId, $teamPublicId, $type),
            ),
        ));
    }

    /**
     * @param  array{permissionNames: list<string>}  $type
     */
    private function userCanReceiveNotificationType(string $userPublicId, string $teamPublicId, array $type): bool
    {
        foreach ($type['permissionNames'] as $permissionName) {
            if ($this->permissions->check(new EffectivePermissionRequest($userPublicId, $permissionName, $teamPublicId))->allowed) {
                return true;
            }
        }

        return false;
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
