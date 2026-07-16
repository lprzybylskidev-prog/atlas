<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use Illuminate\Http\RedirectResponse;

final readonly class DestroyOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageStore $packages,
    ) {}

    public function __invoke(string $package): RedirectResponse
    {
        $this->packages->deactivate($package);

        return redirect()->route('admin.authorization.packages.index')->with('success', 'Preset was deactivated.');
    }
}
