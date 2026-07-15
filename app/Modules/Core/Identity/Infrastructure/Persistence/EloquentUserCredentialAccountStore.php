<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Commands\CreateUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountStore;
use App\Modules\Core\Identity\Application\Public\DTOs\CreatedUserCredentialAccount;

final class EloquentUserCredentialAccountStore implements UserCredentialAccountStore
{
    public function __construct(
        private readonly UserCredentialAccountMapper $mapper,
    ) {}

    public function existsByEmail(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function createAwaitingFirstPassword(CreateUserCredentialAccount $command): CreatedUserCredentialAccount
    {
        $user = User::query()->create([
            'name' => $command->name,
            'email' => $command->email,
            'password' => $command->internalPassword,
            'email_verified_at' => null,
            'first_password_set_at' => null,
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        return $this->mapper->created($user);
    }
}
