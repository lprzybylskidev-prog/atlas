<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Presentation\Http\Controllers;

use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UpdateThemePreferenceController
{
    public function __construct(
        private EffectiveSettings $settings,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'theme' => ['required', 'string', 'in:light,dark'],
        ]);

        $theme = $request->string('theme')->toString();
        $userId = $request->user()?->getAuthIdentifier();

        if (is_int($userId)) {
            $this->settings->setUserTheme($userId, $theme);
        }

        return redirect()->back(303)->withCookie(cookie(
            name: 'atlas_theme',
            value: $theme,
            minutes: 60 * 24 * 365,
            httpOnly: false,
            sameSite: 'lax',
        ));
    }
}
