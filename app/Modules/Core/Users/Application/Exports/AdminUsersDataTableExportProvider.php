<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminUsersDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
    ) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::USERS;
    }

    public function tableName(): string
    {
        return 'Admin users';
    }

    public function owningModuleKey(): string
    {
        return 'users';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-users-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'name' => 'Name',
            'email' => 'Email',
            'isActive' => 'Active',
            'emailVerified' => 'Email verified',
            'firstPasswordSet' => 'First password set',
            'loginLocked' => 'Login locked',
            'mfaEnabled' => 'MFA enabled',
            'online' => 'Online',
            'accountSensitivity' => 'Account sensitivity',
            'emailVerifiedAt' => 'Email verified at',
            'twoFactorConfirmedAt' => 'MFA confirmed at',
            'firstPasswordSetAt' => 'First password set at',
            'deactivatedAt' => 'Deactivated at',
            'failedLoginAttempts' => 'Failed login attempts',
            'loginLockCount' => 'Login lock count',
            'loginLockedUntil' => 'Login locked until',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(
            static fn (AdminUserCredentialAccount $account): array => [
                'id' => $account->id,
                'publicId' => $account->publicId,
                'name' => $account->name,
                'email' => $account->email,
                'isActive' => $account->isActive,
                'emailVerified' => $account->emailVerified,
                'firstPasswordSet' => $account->firstPasswordSet,
                'loginLocked' => $account->loginLocked,
                'mfaEnabled' => $account->mfaEnabled,
                'online' => $account->online,
                'accountSensitivity' => $account->accountSensitivity,
                'emailVerifiedAt' => $account->emailVerifiedAt,
                'twoFactorConfirmedAt' => $account->twoFactorConfirmedAt,
                'firstPasswordSetAt' => $account->firstPasswordSetAt,
                'deactivatedAt' => $account->deactivatedAt,
                'failedLoginAttempts' => $account->failedLoginAttempts,
                'loginLockCount' => $account->loginLockCount,
                'loginLockedUntil' => $account->loginLockedUntil,
                'createdAt' => $account->createdAt,
                'updatedAt' => $account->updatedAt,
            ],
            $this->accounts->allAdminRows(),
        );

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            if (self::filterValue($request, 'status') === 'active' && $row['isActive'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'status') === 'inactive' && $row['isActive'] === true) {
                return false;
            }

            if (self::filterValue($request, 'email') === 'verified' && $row['emailVerified'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'email') === 'unverified' && $row['emailVerified'] === true) {
                return false;
            }

            if (self::filterValue($request, 'password') === 'set' && $row['firstPasswordSet'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'password') === 'pending' && $row['firstPasswordSet'] === true) {
                return false;
            }

            if (self::filterValue($request, 'mfa') === 'enabled' && $row['mfaEnabled'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'mfa') === 'disabled' && $row['mfaEnabled'] === true) {
                return false;
            }

            if (self::filterValue($request, 'lock') === 'locked' && $row['loginLocked'] !== true) {
                return false;
            }

            if (self::filterValue($request, 'lock') === 'unlocked' && $row['loginLocked'] === true) {
                return false;
            }

            $sensitivity = self::filterValue($request, 'sensitivity');

            return $sensitivity === 'all' || $row['accountSensitivity'] === $sensitivity;
        }));
    }
}
