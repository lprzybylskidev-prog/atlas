<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Presentation\Http\Controllers;

use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UpdateLocalePreferenceController
{
    public function __construct(
        private EffectiveSettings $settings,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:pl,en'],
        ]);

        $locale = $request->string('locale')->toString();
        $userId = $request->user()?->getAuthIdentifier();

        if (is_int($userId)) {
            $this->settings->setUserLocale($userId, $locale);
        }

        app()->setLocale($locale);

        return redirect()->back(303)->withCookie(cookie(
            name: 'atlas_locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
