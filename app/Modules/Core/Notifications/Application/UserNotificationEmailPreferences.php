<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use DateTimeInterface;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class UserNotificationEmailPreferences implements NotificationEmailPreferenceManager
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
    ) {}

    /**
     * @return list<array{publicId: string, email: string, primary: bool, verified: bool, verifiedAt: string|null, pendingVerification: bool, enabledTypes: list<string>}>
     */
    public function addressesForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, ?string $teamPublicId): array
    {
        $teamId = $this->teamId($teamPublicId);
        $this->ensurePrimaryAddressForUser($userId, $primaryEmail, $primaryEmailVerifiedAt, $teamId);
        $this->ensureAllAddressPreferences($userId, $teamId);

        $addresses = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' as addresses')
            ->where('addresses.user_id', $userId)
            ->where('addresses.team_id', $teamId)
            ->orderByDesc('addresses.primary')
            ->orderBy('addresses.email')
            ->get()
            ->map(function (object $address) use ($teamId): array {
                $values = get_object_vars($address);
                $addressId = $this->intValue($values['id'] ?? null);
                $enabledTypes = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
                    ->where('notification_email_address_id', $addressId)
                    ->where('team_id', $teamId)
                    ->where('enabled', true)
                    ->pluck('notification_type')
                    ->filter(static fn (mixed $type): bool => is_string($type))
                    ->values()
                    ->all();

                return [
                    'publicId' => $this->stringValue($values['public_id'] ?? null),
                    'email' => $this->stringValue($values['email'] ?? null),
                    'primary' => $this->boolValue($values['primary'] ?? null),
                    'verified' => $values['verified_at'] !== null,
                    'verifiedAt' => is_string($values['verified_at'] ?? null) ? $values['verified_at'] : null,
                    'pendingVerification' => $values['verified_at'] === null && $values['verification_token_hash'] !== null,
                    'enabledTypes' => array_values($enabledTypes),
                ];
            })
            ->values()
            ->all();

        return array_values($addresses);
    }

    public function addAddressForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, string $email, ?string $teamPublicId): void
    {
        $teamId = $this->teamId($teamPublicId);
        $email = mb_strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid notification email address.');
        }

        if ($email === mb_strtolower($primaryEmail)) {
            $this->ensurePrimaryAddressForUser($userId, $primaryEmail, $primaryEmailVerifiedAt, $teamId);

            return;
        }

        $now = now();
        $token = Str::random(64);
        $existingId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('email', $email)
            ->value('id');

        if (is_numeric($existingId)) {
            $addressId = (int) $existingId;
            $verified = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
                ->where('id', $addressId)
                ->whereNotNull('verified_at')
                ->exists();

            if ($verified) {
                $this->insertDefaultPreferences($addressId, $teamId);

                return;
            }

            DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
                ->where('id', $addressId)
                ->update([
                    'verification_token_hash' => Hash::make($token),
                    'verification_sent_at' => $now,
                    'updated_at' => $now,
                ]);
        } else {
            $addressId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->insertGetId([
                'public_id' => (string) Str::ulid(),
                'user_id' => $userId,
                'team_id' => $teamId,
                'email' => $email,
                'primary' => false,
                'verified_at' => null,
                'verification_token_hash' => Hash::make($token),
                'verification_sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->insertDefaultPreferences($addressId, $teamId);

        $verificationUrl = URL::temporarySignedRoute(
            'users.profile.notification-emails.verify',
            now()->addHours(24),
            ['email' => $this->addressPublicId($addressId), 'token' => $token],
        );

        Mail::raw(__('mail.notification_email_verification.body', ['url' => $verificationUrl]), function (Message $message) use ($email): void {
            $message->to($email)->subject(__('mail.notification_email_verification.subject'));
        });
    }

    /**
     * @param  list<string>  $enabledTypes
     * @param  list<string>|null  $knownTypes
     */
    public function updatePreferencesForUser(int $userId, string $addressPublicId, array $enabledTypes, ?array $knownTypes = null, ?string $teamPublicId = null): void
    {
        $teamId = $this->teamId($teamPublicId);
        $knownTypes ??= $this->catalog->names();
        $enabled = array_values(array_intersect($knownTypes, $enabledTypes));

        $address = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('public_id', $addressPublicId)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first(['id']);

        if (! is_object($address)) {
            throw new InvalidArgumentException('Notification email address does not exist.');
        }

        $addressId = $this->intValue(get_object_vars($address)['id'] ?? null);
        $this->insertDefaultPreferences($addressId, $teamId);

        foreach ($knownTypes as $type) {
            DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
                ->where('notification_email_address_id', $addressId)
                ->where('team_id', $teamId)
                ->where('notification_type', $type)
                ->update([
                    'enabled' => in_array($type, $enabled, true),
                    'updated_at' => now(),
                ]);
        }
    }

    public function verifyForUser(int $userId, string $addressPublicId, string $token): bool
    {
        $address = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('public_id', $addressPublicId)
            ->where('user_id', $userId)
            ->first(['id', 'verification_token_hash']);

        if (! is_object($address)) {
            return false;
        }

        $values = get_object_vars($address);
        $hash = $values['verification_token_hash'] ?? null;

        if (! is_string($hash) || ! Hash::check($token, $hash)) {
            return false;
        }

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('id', $this->intValue($values['id'] ?? null))
            ->update([
                'verified_at' => now(),
                'verification_token_hash' => null,
                'updated_at' => now(),
            ]);

        return true;
    }

    public function ensurePrimaryAddressForUser(int $userId, string $primaryEmail, ?DateTimeInterface $primaryEmailVerifiedAt, ?int $teamId = null): void
    {
        $email = mb_strtolower($primaryEmail);
        $now = now();
        $existingId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('email', $email)
            ->value('id');

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('email', '!=', $email)
            ->where('primary', true)
            ->update([
                'primary' => false,
                'updated_at' => $now,
            ]);

        if (is_numeric($existingId)) {
            DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
                ->where('id', (int) $existingId)
                ->update([
                    'primary' => true,
                    'verified_at' => $primaryEmailVerifiedAt ?? $now,
                    'verification_token_hash' => null,
                    'verification_sent_at' => null,
                    'updated_at' => $now,
                ]);

            $this->insertDefaultPreferences((int) $existingId, $teamId);

            return;
        }

        $addressId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'team_id' => $teamId,
            'email' => $email,
            'primary' => true,
            'verified_at' => $primaryEmailVerifiedAt ?? $now,
            'verification_token_hash' => null,
            'verification_sent_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertDefaultPreferences((int) $addressId, $teamId);
    }

    private function ensureAllAddressPreferences(int $userId, ?int $teamId): void
    {
        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->pluck('id')
            ->each(fn (mixed $id): mixed => is_numeric($id) ? $this->insertDefaultPreferences((int) $id, $teamId) : null);
    }

    private function insertDefaultPreferences(int $addressId, ?int $teamId): void
    {
        foreach ($this->catalog->names() as $type) {
            $exists = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
                ->where('notification_email_address_id', $addressId)
                ->where('team_id', $teamId)
                ->where('notification_type', $type)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)->insert([
                'notification_email_address_id' => $addressId,
                'team_id' => $teamId,
                'notification_type' => $type,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function addressPublicId(int $addressId): string
    {
        $publicId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('id', $addressId)
            ->value('public_id');

        return $this->stringValue($publicId);
    }

    private function teamId(?string $teamPublicId): ?int
    {
        if ($teamPublicId === null || $teamPublicId === '') {
            return null;
        }

        $id = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $value === '1' || $value === 't' || $value === 'true';
    }
}
