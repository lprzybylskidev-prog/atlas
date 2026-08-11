<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

interface UserProfileAvatarUpdater
{
    public function updateAvatar(int $userId, ?string $avatarColor, ?string $avatarImageFilePublicId): void;
}
