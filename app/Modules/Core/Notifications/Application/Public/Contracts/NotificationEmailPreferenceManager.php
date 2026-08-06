<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use DateTimeInterface;

interface NotificationEmailPreferenceManager
{
    /**
     * @return list<array{publicId: string, email: string, primary: bool, verified: bool, verifiedAt: string|null, pendingVerification: bool, enabledTypes: list<string>}>
     */
    public function addressesForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, ?string $teamPublicId): array;

    public function addAddressForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, string $email, ?string $teamPublicId): void;

    /**
     * @param  list<string>  $enabledTypes
     * @param  list<string>|null  $knownTypes
     */
    public function updatePreferencesForUser(int $userId, string $addressPublicId, array $enabledTypes, ?array $knownTypes = null, ?string $teamPublicId = null): void;

    public function verifyForUser(int $userId, string $addressPublicId, string $token): bool;

    public function ensurePrimaryAddressForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, ?int $teamId = null): void;
}
