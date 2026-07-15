<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class DatabaseWebAuthnCredentialRepository implements WebAuthnCredentialRepository
{
    public function save(string $userPublicId, CredentialRecord $credential, string $label, bool $hardwareBacked): void
    {
        DB::table('user_webauthn_credentials')->updateOrInsert([
            'credential_id' => $credential->publicKeyCredentialId,
        ], [
            'public_id' => (string) Str::ulid(),
            'user_public_id' => $userPublicId,
            'label' => $label,
            'type' => $credential->type,
            'transports' => json_encode($credential->transports, JSON_THROW_ON_ERROR),
            'attestation_type' => $credential->attestationType,
            'aaguid' => $credential->aaguid->toRfc4122(),
            'credential_public_key' => $credential->credentialPublicKey,
            'user_handle' => $credential->userHandle,
            'counter' => $credential->counter,
            'backup_eligible' => $credential->backupEligible,
            'backup_status' => $credential->backupStatus,
            'uv_initialized' => $credential->uvInitialized,
            'hardware_backed' => $hardwareBacked,
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findByCredentialId(string $credentialId): ?CredentialRecord
    {
        $record = DB::table('user_webauthn_credentials')
            ->where('credential_id', $credentialId)
            ->first();

        return $record === null ? null : $this->hydrateCredentialRecord($record);
    }

    public function allForUser(string $userPublicId): array
    {
        return array_values(DB::table('user_webauthn_credentials')
            ->where('user_public_id', $userPublicId)
            ->orderBy('id')
            ->get()
            ->map(fn (object $record): CredentialRecord => $this->hydrateCredentialRecord($record))
            ->all());
    }

    private function hydrateCredentialRecord(object $record): CredentialRecord
    {
        $record = $this->recordArray($record);

        $transports = json_decode($this->stringValue($record, 'transports'), true, flags: JSON_THROW_ON_ERROR);
        $transports = is_array($transports) ? array_values(array_filter($transports, 'is_string')) : [];

        return CredentialRecord::create(
            publicKeyCredentialId: $this->stringValue($record, 'credential_id'),
            type: $this->stringValue($record, 'type'),
            transports: $transports,
            attestationType: $this->stringValue($record, 'attestation_type'),
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString($this->stringValue($record, 'aaguid')),
            credentialPublicKey: $this->stringValue($record, 'credential_public_key'),
            userHandle: $this->stringValue($record, 'user_handle'),
            counter: $this->intValue($record, 'counter'),
            backupEligible: $this->nullableBoolValue($record, 'backup_eligible'),
            backupStatus: $this->nullableBoolValue($record, 'backup_status'),
            uvInitialized: $this->nullableBoolValue($record, 'uv_initialized'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function recordArray(object $record): array
    {
        $values = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function stringValue(array $record, string $key): string
    {
        $value = $record[$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException(sprintf('WebAuthn credential field [%s] must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function intValue(array $record, string $key): int
    {
        $value = $record[$key] ?? null;

        if (! is_int($value)) {
            throw new RuntimeException(sprintf('WebAuthn credential field [%s] must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableBoolValue(array $record, string $key): ?bool
    {
        $value = $record[$key] ?? null;

        if ($value === null || is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        throw new RuntimeException(sprintf('WebAuthn credential field [%s] must be a nullable boolean.', $key));
    }
}
