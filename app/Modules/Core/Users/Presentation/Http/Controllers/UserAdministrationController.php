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

        $filters = $this->filters($request);
        $rows = array_map(fn (AdminUserCredentialAccount $user): array => $this->row($request, $user, $actorPublicId), $this->accounts->allAdminRows());
        $result = $this->tables->process($this->applyFilters($rows, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Users/Index', [
            'users' => $result->rows,
            'table' => $table,
        ]);
    }

    /**
     * @return array{status: string, email: string, password: string, mfa: string, lock: string, sensitivity: string}
     */
    private function filters(Request $request): array
    {
        return [
            'status' => $this->oneOf($request->query('status'), ['all', 'active', 'inactive']),
            'email' => $this->oneOf($request->query('email'), ['all', 'verified', 'unverified']),
            'password' => $this->oneOf($request->query('password'), ['all', 'set', 'pending']),
            'mfa' => $this->oneOf($request->query('mfa'), ['all', 'enabled', 'disabled']),
            'lock' => $this->oneOf($request->query('lock'), ['all', 'locked', 'unlocked']),
            'sensitivity' => $this->oneOf($request->query('sensitivity'), ['all', 'normal', 'sensitive', 'technical', 'service', 'integration']),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{status: string, email: string, password: string, mfa: string, lock: string, sensitivity: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'active' && $row['isActive'] !== true) {
                return false;
            }

            if ($filters['status'] === 'inactive' && $row['isActive'] === true) {
                return false;
            }

            if ($filters['email'] === 'verified' && $row['emailVerified'] !== true) {
                return false;
            }

            if ($filters['email'] === 'unverified' && $row['emailVerified'] === true) {
                return false;
            }

            if ($filters['password'] === 'set' && $row['firstPasswordSet'] !== true) {
                return false;
            }

            if ($filters['password'] === 'pending' && $row['firstPasswordSet'] === true) {
                return false;
            }

            if ($filters['mfa'] === 'enabled' && $row['mfaEnabled'] !== true) {
                return false;
            }

            if ($filters['mfa'] === 'disabled' && $row['mfaEnabled'] === true) {
                return false;
            }

            if ($filters['lock'] === 'locked' && $row['loginLocked'] !== true) {
                return false;
            }

            if ($filters['lock'] === 'unlocked' && $row['loginLocked'] === true) {
                return false;
            }

            return $filters['sensitivity'] === 'all' || $row['accountSensitivity'] === $filters['sensitivity'];
        }));
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
