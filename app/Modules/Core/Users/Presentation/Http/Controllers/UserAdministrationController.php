<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationEligibilityChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Database\Eloquent\Model;
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
        private ImpersonationEligibilityChecker $impersonation,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::USERS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $actorPublicId = $this->actorPublicId($request);

        $result = $this->tables->process(array_map(fn (AdminUserCredentialAccount $user): array => $this->row($request, $user, $actorPublicId), $this->accounts->allAdminRows()), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Users/Index', [
            'users' => $result->rows,
            'table' => $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults()),
        ]);
    }

    /**
     * @return array{id: int, publicId: string, name: string, email: string, isActive: bool, emailVerified: bool, firstPasswordSet: bool, loginLocked: bool, mfaEnabled: bool, online: bool, accountSensitivity: string, canImpersonate: bool, impersonationRequiresSensitiveOverride: bool, emailVerifiedAt: string|null, twoFactorConfirmedAt: string|null, firstPasswordSetAt: string|null, deactivatedAt: string|null, failedLoginAttempts: int, loginLockCount: int, loginLockedUntil: string|null, createdAt: string, updatedAt: string}
     */
    private function row(Request $request, AdminUserCredentialAccount $user, ?string $actorPublicId): array
    {
        $eligibility = $actorPublicId !== null
            ? $this->impersonation->eligibility($request, $actorPublicId, $user->publicId)
            : null;

        return [
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
            'canImpersonate' => $eligibility !== null && $eligibility->canStart,
            'impersonationRequiresSensitiveOverride' => $eligibility !== null && $eligibility->requiresSensitiveOverride,
            'emailVerifiedAt' => $user->emailVerifiedAt,
            'twoFactorConfirmedAt' => $user->twoFactorConfirmedAt,
            'firstPasswordSetAt' => $user->firstPasswordSetAt,
            'deactivatedAt' => $user->deactivatedAt,
            'failedLoginAttempts' => $user->failedLoginAttempts,
            'loginLockCount' => $user->loginLockCount,
            'loginLockedUntil' => $user->loginLockedUntil,
            'createdAt' => $user->createdAt,
            'updatedAt' => $user->updatedAt,
        ];
    }

    private function actorPublicId(Request $request): ?string
    {
        $actor = $request->user();
        $publicId = $actor instanceof Model ? $actor->getAttribute('public_id') : null;

        return is_string($publicId) ? $publicId : null;
    }
}
