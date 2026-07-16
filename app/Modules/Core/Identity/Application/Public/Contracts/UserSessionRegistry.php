<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Sessions\UserSessionMetadata;
use Illuminate\Http\Request;

interface UserSessionRegistry
{
    public function touch(Request $request): void;

    /**
     * @return list<UserSessionMetadata>
     */
    public function activeForUser(string $userPublicId): array;

    /**
     * @return list<string>
     */
    public function onlineUserPublicIds(): array;

    public function terminate(string $sessionId): void;

    public function terminateOtherSessions(string $userPublicId, string $currentSessionId): void;

    public function invalidateUser(string $userPublicId): void;

    public function invalidateUserTeam(string $userPublicId, string $teamPublicId): void;
}
