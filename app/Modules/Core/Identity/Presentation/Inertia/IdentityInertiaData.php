<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Inertia;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Shared\Application\Files\Contracts\FileAvailability;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;

final readonly class IdentityInertiaData implements InertiaSharedDataContributor
{
    public function __construct(
        private FileAvailability $files,
        private ImpersonationManager $impersonation,
        private AdministrativeSessionManager $administrativeSessions,
    ) {}

    public function key(): string
    {
        return 'core.identity';
    }

    public function data(Request $request): array
    {
        $user = $request->user();

        return [
            'auth.user' => $user === null ? null : [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => [
                    'color' => $user->avatar_color,
                    'imageUrl' => $this->avatarImageUrl($user->avatar_image_file_public_id),
                ],
            ],
            'auth.impersonation' => $this->impersonation->sharedState($request),
            'auth.adminMode' => [
                'active' => $this->administrativeSessions->active($request),
                'highRiskFresh' => $this->administrativeSessions->highRiskFresh($request),
            ],
        ];
    }

    private function avatarImageUrl(mixed $filePublicId): ?string
    {
        if (! is_string($filePublicId) || $filePublicId === '') {
            return null;
        }

        return $this->files->clean($filePublicId) ? route('users.profile.avatar-image', absolute: false) : null;
    }
}
