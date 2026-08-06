<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Lifecycle;

use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class UserAccountDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $user = $this->user($subject);

        if ($user === null) {
            return new DataLifecyclePreview([]);
        }

        return new DataLifecyclePreview([
            new DataLifecycleImpact(
                'identity.users',
                1,
                true,
                [[
                    'id' => $user['id'],
                    'public_id' => $user['public_id'],
                    'email' => $user['email'],
                    'is_active' => $user['is_active'],
                    'email_verified_at' => $user['email_verified_at'],
                    'deactivated_at' => $user['deactivated_at'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'],
                ]],
            ),
            new DataLifecycleImpact(
                'identity.user_password_histories',
                $this->passwordHistories($user['id'])->count(),
                true,
                $this->records($this->passwordHistories($user['id']), ['id', 'user_id', 'created_at']),
            ),
            new DataLifecycleImpact(
                'identity.password_reset_tokens',
                $this->passwordResetTokens($user['email'])->count(),
                true,
                $this->records($this->passwordResetTokens($user['email']), ['email', 'created_at']),
            ),
            new DataLifecycleImpact(
                'identity.webauthn_credentials',
                $this->webAuthnCredentials($user['public_id'])->count(),
                true,
                $this->records($this->webAuthnCredentials($user['public_id']), [
                    'id',
                    'public_id',
                    'user_public_id',
                    'label',
                    'type',
                    'attestation_type',
                    'aaguid',
                    'counter',
                    'backup_eligible',
                    'backup_status',
                    'uv_initialized',
                    'hardware_backed',
                    'last_used_at',
                    'created_at',
                ]),
            ),
            new DataLifecycleImpact(
                'identity.sessions',
                $this->sessions($user['id'])->count(),
                true,
                $this->records($this->sessions($user['id']), ['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']),
            ),
        ]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $user = $this->user($subject);

        if ($user === null) {
            return new DataLifecycleResult([]);
        }

        $steps = [];
        $steps[] = new DataLifecycleStepResult('identity.sessions_removed', $this->sessions($user['id'])->delete(), true);
        $steps[] = new DataLifecycleStepResult('identity.password_reset_tokens_removed', $this->passwordResetTokens($user['email'])->delete(), true);
        $steps[] = new DataLifecycleStepResult('identity.user_password_histories_removed', $this->passwordHistories($user['id'])->delete(), true);
        $steps[] = new DataLifecycleStepResult('identity.webauthn_credentials_removed', $this->webAuthnCredentials($user['public_id'])->delete(), true);

        $updates = [
            'name' => 'Redacted user',
            'email' => sprintf('redacted-%s@redacted.atlas.invalid', strtolower($user['public_id'])),
            'email_verified_at' => null,
            'password' => Hash::make((string) Str::ulid()),
            'first_password_set_at' => null,
            'is_active' => false,
            'deactivated_at' => now(),
            'failed_login_attempts' => 0,
            'login_lock_count' => 0,
            'login_locked_until' => null,
            'remember_token' => null,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn(DatabaseTable::USERS, 'two_factor_secret')) {
            $updates['two_factor_secret'] = null;
            $updates['two_factor_recovery_codes'] = null;
            $updates['two_factor_confirmed_at'] = null;
        }

        if (Schema::hasColumn(DatabaseTable::USERS, 'account_sensitivity')) {
            $updates['account_sensitivity'] = 'normal';
        }

        $steps[] = new DataLifecycleStepResult(
            'identity.user_account_redacted',
            $this->db->table(DatabaseTable::USERS)->where('id', $user['id'])->update($updates),
            true,
        );

        return new DataLifecycleResult($steps);
    }

    /**
     * @return array{id: int, public_id: string, email: string, is_active: bool, email_verified_at: mixed, deactivated_at: mixed, created_at: mixed, updated_at: mixed}|null
     */
    private function user(DataLifecycleSubject $subject): ?array
    {
        if ($subject->type !== 'user') {
            return null;
        }

        $user = $this->db->table(DatabaseTable::USERS)
            ->where('public_id', $subject->identifier)
            ->first([
                'id',
                'public_id',
                'email',
                'email_verified_at',
                'is_active',
                'deactivated_at',
                'created_at',
                'updated_at',
            ]);

        if (! is_object($user)) {
            return null;
        }

        $values = (array) $user;
        $id = $values['id'] ?? null;
        $publicId = $values['public_id'] ?? null;
        $email = $values['email'] ?? null;

        if (! is_numeric($id) || ! is_string($publicId) || $publicId === '' || ! is_string($email) || $email === '') {
            return null;
        }

        return [
            'id' => (int) $id,
            'public_id' => $publicId,
            'email' => $email,
            'is_active' => (bool) ($values['is_active'] ?? false),
            'email_verified_at' => $values['email_verified_at'] ?? null,
            'deactivated_at' => $values['deactivated_at'] ?? null,
            'created_at' => $values['created_at'] ?? null,
            'updated_at' => $values['updated_at'] ?? null,
        ];
    }

    private function passwordHistories(int $userId): Builder
    {
        return $this->db->table(DatabaseTable::USER_PASSWORD_HISTORIES)->where('user_id', $userId);
    }

    private function passwordResetTokens(string $email): Builder
    {
        return $this->db->table(DatabaseTable::PASSWORD_RESET_TOKENS)->where('email', $email);
    }

    private function webAuthnCredentials(string $userPublicId): Builder
    {
        return $this->db->table(DatabaseTable::USER_WEBAUTHN_CREDENTIALS)->where('user_public_id', $userPublicId);
    }

    private function sessions(int $userId): Builder
    {
        return $this->db->table(DatabaseTable::SESSIONS)->where('user_id', $userId);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function records(Builder $query, array $columns): array
    {
        $records = [];

        foreach ($query->get($columns) as $record) {
            $records[] = $this->recordToArray($record);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(object $record): array
    {
        $row = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
