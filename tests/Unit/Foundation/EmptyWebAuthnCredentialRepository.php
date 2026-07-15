<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use Webauthn\CredentialRecord;

final class EmptyWebAuthnCredentialRepository implements WebAuthnCredentialRepository
{
    public function save(string $userPublicId, CredentialRecord $credential, string $label, bool $hardwareBacked): void {}

    public function findByCredentialId(string $credentialId): ?CredentialRecord
    {
        return null;
    }

    public function allForUser(string $userPublicId): array
    {
        return [];
    }
}
