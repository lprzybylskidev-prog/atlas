<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\TrustPath\EmptyTrustPath;

final class WebAuthnCredentialRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webauthn_passkey_or_fido2_credential_can_be_stored_and_loaded(): void
    {
        $user = User::factory()->create();
        $credential = CredentialRecord::create(
            publicKeyCredentialId: 'credential-id-1',
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: ['usb', 'internal'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::v4(),
            credentialPublicKey: 'public-key-bytes',
            userHandle: $user->public_id,
            counter: 12,
            backupEligible: true,
            backupStatus: false,
            uvInitialized: true,
        );

        $repository = $this->app->make(WebAuthnCredentialRepository::class);
        $repository->save($user->public_id, $credential, 'Primary passkey', hardwareBacked: true);

        $loaded = $repository->findByCredentialId('credential-id-1');

        self::assertInstanceOf(CredentialRecord::class, $loaded);
        self::assertSame('credential-id-1', $loaded->publicKeyCredentialId);
        self::assertSame(['usb', 'internal'], $loaded->transports);
        self::assertSame('public-key-bytes', $loaded->credentialPublicKey);
        self::assertSame(12, $loaded->counter);

        self::assertCount(1, $repository->allForUser($user->public_id));

        $this->assertDatabaseHas('user_webauthn_credentials', [
            'user_public_id' => $user->public_id,
            'credential_id' => 'credential-id-1',
            'label' => 'Primary passkey',
            'hardware_backed' => true,
        ]);
    }
}
