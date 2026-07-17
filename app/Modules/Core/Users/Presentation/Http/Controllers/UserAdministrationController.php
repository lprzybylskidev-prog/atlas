<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserAdministrationController
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::USERS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $result = $this->tables->process(array_map(static fn (AdminUserCredentialAccount $user): array => [
            'id' => $user->id,
            'publicId' => $user->publicId,
            'name' => $user->name,
            'email' => $user->email,
            'isActive' => $user->isActive,
            'emailVerified' => $user->emailVerified,
            'firstPasswordSet' => $user->firstPasswordSet,
            'loginLocked' => $user->loginLocked,
            'mfaEnabled' => $user->mfaEnabled,
            'online' => $user->online,
            'accountSensitivity' => $user->accountSensitivity,
            'emailVerifiedAt' => $user->emailVerifiedAt,
            'twoFactorConfirmedAt' => $user->twoFactorConfirmedAt,
            'firstPasswordSetAt' => $user->firstPasswordSetAt,
            'deactivatedAt' => $user->deactivatedAt,
            'failedLoginAttempts' => $user->failedLoginAttempts,
            'loginLockCount' => $user->loginLockCount,
            'loginLockedUntil' => $user->loginLockedUntil,
            'createdAt' => $user->createdAt,
            'updatedAt' => $user->updatedAt,
        ], $this->accounts->allAdminRows()), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Users/Index', [
            'users' => $result->rows,
            'table' => $result->tableMeta($definition->key),
        ]);
    }
}
