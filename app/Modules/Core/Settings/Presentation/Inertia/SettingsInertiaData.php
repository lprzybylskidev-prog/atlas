<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Presentation\Inertia;

use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\TeamLookup;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;

final readonly class SettingsInertiaData implements InertiaSharedDataContributor
{
    public function __construct(
        private EffectiveSettings $settings,
        private TeamLookup $teams,
    ) {}

    public function key(): string
    {
        return 'core.settings';
    }

    public function data(Request $request): array
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = is_string($teamPublicId) ? $this->teams->internalIdForPublicId($teamPublicId) : null;
        $userId = $request->user()?->getAuthIdentifier();
        $guestTheme = $request->cookie('atlas_theme');

        return [
            'preferences.theme' => $this->settings->theme(
                userId: is_int($userId) ? $userId : null,
                teamId: $teamId,
                guestTheme: is_string($guestTheme) ? $guestTheme : null,
            ),
        ];
    }
}
