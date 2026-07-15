<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Commands\CreateUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStore;
use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\Contracts\UserAccountRepository;
use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;

final readonly class IdentityUserAccountRepository implements UserAccountRepository
{
    public function __construct(
        private UserCredentialAccountStore $credentials,
    ) {}

    public function existsByEmail(string $email): bool
    {
        return $this->credentials->existsByEmail($email);
    }

    public function createAwaitingFirstPassword(CreateUserAccountCommand $command, string $internalPassword): CreatedUserAccount
    {
        $created = $this->credentials->createAwaitingFirstPassword(new CreateUserCredentialAccount(
            name: $command->name,
            email: $command->email,
            internalPassword: $internalPassword,
        ));

        return new CreatedUserAccount(
            publicId: $created->publicId,
            name: $created->name,
            email: $created->email,
            firstPasswordLinkIssued: false,
        );
    }
}
