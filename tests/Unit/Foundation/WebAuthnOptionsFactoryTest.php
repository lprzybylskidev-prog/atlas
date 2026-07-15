<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use App\Modules\Core\Identity\Application\WebAuthn\WebAuthnOptionsFactory;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Webauthn\AuthenticatorSelectionCriteria;

final class WebAuthnOptionsFactoryTest extends TestCase
{
    public function test_passkey_and_hardware_key_registration_options_use_expected_authenticator_attachment(): void
    {
        Config::set('atlas.security.webauthn.rp_id', 'atlas.example.test');
        Config::set('atlas.security.webauthn.rp_name', 'Atlas');

        $this->app->bind(WebAuthnCredentialRepository::class, static fn (): WebAuthnCredentialRepository => new EmptyWebAuthnCredentialRepository);

        $factory = $this->app->make(WebAuthnOptionsFactory::class);

        $passkey = $factory->passkeyRegistrationOptions('01HZY000000000000000000000', 'user@example.test', 'User Example');
        $hardwareKey = $factory->hardwareKeyRegistrationOptions('01HZY000000000000000000000', 'user@example.test', 'User Example');

        self::assertNotNull($passkey->authenticatorSelection);
        self::assertNotNull($hardwareKey->authenticatorSelection);
        self::assertSame('atlas.example.test', $passkey->rp->id);
        self::assertSame(AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM, $passkey->authenticatorSelection->authenticatorAttachment);
        self::assertSame(AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED, $passkey->authenticatorSelection->residentKey);
        self::assertSame(AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM, $hardwareKey->authenticatorSelection->authenticatorAttachment);
    }
}
