<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application;

use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\Contracts\FirstPasswordLinkIssuer;
use App\Modules\Core\Users\Application\Contracts\UserAccountRepository;
use App\Modules\Core\Users\Application\DTOs\CreatedUserAccount;
use App\Modules\Core\Users\Application\Exceptions\InvalidUserAccountData;
use Illuminate\Support\Str;

final readonly class CreateUserAccount
{
    public function __construct(
        private UserAccountRepository $accounts,
        private FirstPasswordLinkIssuer $firstPasswordLinks,
    ) {}

    public function handle(CreateUserAccountCommand $command): CreatedUserAccount
    {
        $normalized = new CreateUserAccountCommand(
            name: trim($command->name),
            email: Str::lower(trim($command->email)),
        );

        if ($normalized->name === '') {
            throw InvalidUserAccountData::missingName();
        }

        if (filter_var($normalized->email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidUserAccountData::invalidEmail();
        }

        if ($this->accounts->existsByEmail($normalized->email)) {
            throw InvalidUserAccountData::duplicateEmail($normalized->email);
        }

        $account = $this->accounts->createAwaitingFirstPassword(
            command: $normalized,
            internalPassword: Str::password(length: 64),
        );

        $this->firstPasswordLinks->issue($account->email);

        return new CreatedUserAccount(
            publicId: $account->publicId,
            name: $account->name,
            email: $account->email,
            firstPasswordLinkIssued: true,
        );
    }
}
