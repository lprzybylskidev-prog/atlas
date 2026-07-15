<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\WebAuthn;

use App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

final readonly class WebAuthnOptionsFactory
{
    public function __construct(
        private WebAuthnCredentialRepository $credentials,
    ) {}

    public function passkeyRegistrationOptions(string $userPublicId, string $email, string $displayName): PublicKeyCredentialCreationOptions
    {
        return $this->registrationOptions(
            userPublicId: $userPublicId,
            email: $email,
            displayName: $displayName,
            authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );
    }

    public function hardwareKeyRegistrationOptions(string $userPublicId, string $email, string $displayName): PublicKeyCredentialCreationOptions
    {
        return $this->registrationOptions(
            userPublicId: $userPublicId,
            email: $email,
            displayName: $displayName,
            authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_CROSS_PLATFORM,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_DISCOURAGED,
        );
    }

    public function authenticationOptions(string $userPublicId): PublicKeyCredentialRequestOptions
    {
        $allowCredentials = array_map(
            static fn ($credential) => $credential->getPublicKeyCredentialDescriptor(),
            $this->credentials->allForUser($userPublicId),
        );

        return PublicKeyCredentialRequestOptions::create(
            challenge: $this->challenge(),
            rpId: $this->rpId(),
            allowCredentials: $allowCredentials,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: $this->timeout(),
        );
    }

    private function registrationOptions(
        string $userPublicId,
        string $email,
        string $displayName,
        ?string $authenticatorAttachment,
        ?string $residentKey,
    ): PublicKeyCredentialCreationOptions {
        return PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create($this->rpName(), $this->rpId()),
            user: PublicKeyCredentialUserEntity::create($email, $userPublicId, $displayName),
            challenge: $this->challenge(),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: $authenticatorAttachment,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: $residentKey,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: array_map(
                static fn ($credential) => $credential->getPublicKeyCredentialDescriptor(),
                $this->credentials->allForUser($userPublicId),
            ),
            timeout: $this->timeout(),
        );
    }

    private function challenge(): string
    {
        return random_bytes(32);
    }

    private function rpId(): string
    {
        $configured = config('atlas.security.webauthn.rp_id');

        return is_string($configured) && $configured !== '' ? $configured : 'localhost';
    }

    private function rpName(): string
    {
        $configured = config('atlas.security.webauthn.rp_name');

        return is_string($configured) && $configured !== '' ? $configured : 'Atlas';
    }

    /** @return positive-int */
    private function timeout(): int
    {
        $configured = config('atlas.security.webauthn.timeout_ms');

        if (is_int($configured) && $configured > 0) {
            return $configured;
        }

        return 60000;
    }
}
