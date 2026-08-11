<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserProfileAvatarUpdater;

final class EloquentUserProfileAvatarUpdater implements UserProfileAvatarUpdater
{
    public function updateAvatar(int $userId, ?string $avatarColor, ?string $avatarImageFilePublicId): void
    {
        User::query()
            ->whereKey($userId)
            ->update([
                'avatar_color' => $avatarColor,
                'avatar_image_file_public_id' => $avatarImageFilePublicId,
                'updated_at' => now(),
            ]);
    }
}
