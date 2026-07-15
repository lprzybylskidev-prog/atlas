<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\WebAuthn\Contracts;

use Webauthn\CredentialRecord;

interface WebAuthnCredentialRepository
{
    public function save(string $userPublicId, CredentialRecord $credential, string $label, bool $hardwareBacked): void;

    public function findByCredentialId(string $credentialId): ?CredentialRecord;

    /** @return list<CredentialRecord> */
    public function allForUser(string $userPublicId): array;
}
