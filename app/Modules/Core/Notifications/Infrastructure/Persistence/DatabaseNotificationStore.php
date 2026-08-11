<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationEmailPreferenceManager;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationMaintenance;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimeFeed;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationCleanupResult;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Application\Public\DTOs\PublishRealtimeEvent;
use App\Modules\Core\Notifications\Application\Public\DTOs\RealtimeEventSummary;
use App\Modules\Core\Notifications\Infrastructure\Persistence\TableNames\NotificationsDatabaseTable;
use App\Modules\Core\Notifications\Presentation\Jobs\DeliverNotification;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DatabaseNotificationStore implements NotificationInbox, NotificationMaintenance, NotificationPublisher, RealtimeFeed, RealtimePublisher
{
    public function __construct(
        private readonly UserLookup $users,
        private readonly TeamLookup $teams,
    ) {}

    public function publish(CreateNotification $notification): string
    {
        $userId = $this->userId($notification->recipientUserPublicId);

        if (! is_int($userId)) {
            throw new RuntimeException('Notification recipient user does not exist.');
        }

        $teamId = $notification->teamPublicId === null ? null : $this->teamId($notification->teamPublicId);

        if ($notification->teamPublicId !== null && ! is_int($teamId)) {
            throw new RuntimeException('Notification recipient team does not exist.');
        }

        $publicId = (string) Str::ulid();
        $recipientId = DB::transaction(function () use ($notification, $publicId, $userId, $teamId): int {
            $notificationId = DB::table(NotificationsDatabaseTable::NOTIFICATIONS)->insertGetId([
                'public_id' => $publicId,
                'type' => $notification->type,
                'severity' => $notification->severity,
                'title' => $notification->title,
                'body' => $notification->body,
                'deep_link_url' => $notification->deepLinkUrl,
                'data' => json_encode($notification->data, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $recipientId = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)->insertGetId([
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'email_status' => $notification->emailRequested ? 'pending' : 'not_requested',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->insertRealtimeEvent(
                topic: 'notifications',
                eventType: 'notification.created',
                userId: $userId,
                teamId: $teamId,
                payload: ['notification_public_id' => $publicId],
            );

            return $recipientId;
        });

        DeliverNotification::dispatch($recipientId)->afterCommit();

        return $publicId;
    }

    public function publishRealtime(PublishRealtimeEvent $event): string
    {
        $userId = $event->userPublicId === null ? null : $this->userId($event->userPublicId);
        $teamId = $event->teamPublicId === null ? null : $this->teamId($event->teamPublicId);

        return $this->insertRealtimeEvent($event->topic, $event->eventType, $userId, $teamId, $event->payload);
    }

    public function publishSessionInvalidated(string $userPublicId, ?string $teamPublicId = null, ?string $sessionId = null): string
    {
        return $this->publishRealtime(new PublishRealtimeEvent(
            topic: 'sessions',
            eventType: 'session.invalidated',
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            payload: ['session_id' => $sessionId],
        ));
    }

    public function publishSystemAlert(string $title, string $severity = 'info', ?string $body = null, ?string $userPublicId = null, ?string $teamPublicId = null): string
    {
        return $this->publishRealtime(new PublishRealtimeEvent(
            topic: 'system-alerts',
            eventType: 'system.alert',
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            payload: [
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
            ],
        ));
    }

    public function publishOperationProgress(
        string $operationType,
        string $operationId,
        string $status,
        int $progressPercent,
        ?string $userPublicId = null,
        ?string $teamPublicId = null,
        ?string $message = null,
    ): string {
        return $this->publishRealtime(new PublishRealtimeEvent(
            topic: 'operation-progress',
            eventType: 'operation.progress',
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            payload: [
                'operation_type' => $operationType,
                'operation_id' => $operationId,
                'status' => $status,
                'progress_percent' => max(0, min(100, $progressPercent)),
                'message' => $message,
            ],
        ));
    }

    public function latestForUser(string $userPublicId, ?string $teamPublicId, int $limit): array
    {
        return $this->summaries($userPublicId, $teamPublicId, max(1, min(50, $limit)));
    }

    public function allForUser(string $userPublicId, ?string $teamPublicId): array
    {
        return $this->summaries($userPublicId, $teamPublicId, null);
    }

    public function unreadCount(string $userPublicId, ?string $teamPublicId): int
    {
        $userId = $this->userId($userPublicId);

        if (! is_int($userId)) {
            return 0;
        }

        $query = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->where('user_id', $userId)
            ->whereNull('read_at');

        $teamId = $teamPublicId === null ? null : $this->teamId($teamPublicId);

        if (is_int($teamId)) {
            $query->where(static function (Builder $query) use ($teamId): void {
                $query->whereNull('team_id')->orWhere('team_id', $teamId);
            });
        }

        return (int) $query->count();
    }

    public function markRead(string $userPublicId, string $notificationPublicId): void
    {
        $userId = $this->userId($userPublicId);

        if (! is_int($userId)) {
            return;
        }

        DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(NotificationsDatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notification_recipients.user_id', $userId)
            ->where('notifications.public_id', $notificationPublicId)
            ->whereNull('notification_recipients.read_at')
            ->update([
                'read_at' => now(),
                'notification_recipients.updated_at' => now(),
            ]);
    }

    public function emailRequested(int $recipientId): bool
    {
        return DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->where('id', $recipientId)
            ->where('email_status', 'pending')
            ->exists();
    }

    public function markDeliveredInApp(int $recipientId): void
    {
        DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->where('id', $recipientId)
            ->whereNull('delivered_in_app_at')
            ->update([
                'delivered_in_app_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function markEmailSkipped(int $recipientId): void
    {
        DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->where('id', $recipientId)
            ->update([
                'email_status' => 'skipped',
                'updated_at' => now(),
            ]);
    }

    /**
     * @return list<array{email: string, user_id: int, team_id: int|null, titles: array{pl: string, en: string}, bodies: array{pl: string|null, en: string|null}, deep_link_url: string|null}>
     */
    public function emailPayloads(int $recipientId): array
    {
        $record = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS.' as recipients')
            ->join(NotificationsDatabaseTable::NOTIFICATIONS.' as notifications', 'recipients.notification_id', '=', 'notifications.id')
            ->where('recipients.id', $recipientId)
            ->first([
                'recipients.user_id',
                'recipients.team_id',
                'notifications.type',
                'notifications.title',
                'notifications.body',
                'notifications.deep_link_url',
                'notifications.data',
            ]);

        if (! is_object($record)) {
            return [];
        }

        $values = get_object_vars($record);
        $userId = $values['user_id'] ?? null;
        $teamId = $values['team_id'] ?? null;
        $type = $this->scalarString($values['type'] ?? '');
        $title = $this->scalarString($values['title'] ?? '');
        $body = $values['body'] ?? null;
        $deepLinkUrl = $values['deep_link_url'] ?? null;
        $data = $this->decodedPayload($values['data'] ?? '{}');

        if (! is_numeric($userId) || $type === '' || $title === '') {
            return [];
        }

        $user = $this->users->notificationContactForInternalId((int) $userId);

        if ($user !== null) {
            app(NotificationEmailPreferenceManager::class)->ensurePrimaryAddressForUser(
                $user->internalId,
                $user->email,
                $user->emailVerifiedAt,
                is_numeric($teamId) ? (int) $teamId : null,
            );
        }

        $emails = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES.' as addresses')
            ->join(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES.' as preferences', 'preferences.notification_email_address_id', '=', 'addresses.id')
            ->where('addresses.user_id', (int) $userId)
            ->where('addresses.team_id', is_numeric($teamId) ? (int) $teamId : null)
            ->whereNotNull('addresses.verified_at')
            ->where('preferences.notification_type', $type)
            ->where('preferences.team_id', is_numeric($teamId) ? (int) $teamId : null)
            ->where('preferences.enabled', true)
            ->orderByDesc('addresses.primary')
            ->orderBy('addresses.email')
            ->pluck('addresses.email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();
        $payloads = [];

        foreach ($emails as $email) {
            $payloads[] = [
                'email' => $email,
                'user_id' => (int) $userId,
                'team_id' => is_numeric($teamId) ? (int) $teamId : null,
                'titles' => [
                    'pl' => $this->localizedMailText('title', $title, $data, 'pl') ?? $title,
                    'en' => $this->localizedMailText('title', $title, $data, 'en') ?? $title,
                ],
                'bodies' => [
                    'pl' => $this->localizedMailText('body', is_string($body) ? $body : null, $data, 'pl'),
                    'en' => $this->localizedMailText('body', is_string($body) ? $body : null, $data, 'en'),
                ],
                'deep_link_url' => is_string($deepLinkUrl) && $deepLinkUrl !== '' ? url($deepLinkUrl) : null,
            ];
        }

        return $payloads;
    }

    /** @param array<string, mixed> $data */
    private function localizedMailText(string $field, ?string $fallback, array $data, string $locale): ?string
    {
        $localized = $data[$field.'_'.$locale] ?? null;

        if (is_string($localized) && $localized !== '') {
            return $localized;
        }

        $key = $data[$field.'_key'] ?? null;

        if (! is_string($key) || ! str_starts_with($key, 'notifications.')) {
            return $fallback;
        }

        $parameters = [];

        foreach ($data as $name => $value) {
            if (! str_ends_with($name, '_key')
                && ! in_array($name, ['title_pl', 'title_en', 'body_pl', 'body_en'], true)
                && (is_scalar($value) || $value === null)) {
                $parameters[$name] = $value;
            }
        }

        return trans($key, $parameters, $locale);
    }

    public function markEmailDelivered(int $recipientId): void
    {
        DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->where('id', $recipientId)
            ->update([
                'email_status' => 'delivered',
                'delivered_email_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function visibleEvents(string $userPublicId, ?string $teamPublicId, ?string $afterPublicId, int $limit): array
    {
        $userId = $this->userId($userPublicId);

        if (! is_int($userId)) {
            return [];
        }

        $teamId = $teamPublicId === null ? null : $this->teamId($teamPublicId);
        $afterId = $afterPublicId === null ? null : DB::table(NotificationsDatabaseTable::REALTIME_EVENTS)->where('public_id', $afterPublicId)->value('id');

        $query = DB::table(NotificationsDatabaseTable::REALTIME_EVENTS)
            ->where(static function (Builder $query) use ($userId): void {
                $query->whereNull('realtime_events.user_id')->orWhere('realtime_events.user_id', $userId);
            })
            ->orderBy('realtime_events.id')
            ->limit(max(1, min(100, $limit)))
            ->select([
                'realtime_events.public_id',
                'realtime_events.topic',
                'realtime_events.event_type',
                'realtime_events.payload',
                'realtime_events.created_at',
                'realtime_events.team_id',
            ]);

        if (is_int($teamId)) {
            $query->where(static function (Builder $query) use ($teamId): void {
                $query->whereNull('realtime_events.team_id')->orWhere('realtime_events.team_id', $teamId);
            });
        } else {
            $query->whereNull('realtime_events.team_id');
        }

        if (is_numeric($afterId)) {
            $query->where('realtime_events.id', '>', (int) $afterId);
        }

        $events = [];

        foreach ($query->get() as $row) {
            $values = get_object_vars($row);
            $events[] = new RealtimeEventSummary(
                publicId: $this->scalarString($values['public_id'] ?? ''),
                topic: $this->scalarString($values['topic'] ?? ''),
                eventType: $this->scalarString($values['event_type'] ?? ''),
                teamPublicId: is_numeric($values['team_id'] ?? null) ? $this->teams->publicIdForInternalId((int) $values['team_id']) : null,
                payload: $this->decodedPayload($values['payload'] ?? '{}'),
                createdAt: $this->dateTimeString($values['created_at'] ?? null),
            );
        }

        return $events;
    }

    public function prune(int $readRetentionDays, int $realtimeRetentionHours): NotificationCleanupResult
    {
        $readBefore = CarbonImmutable::now()->subDays(max(1, $readRetentionDays));
        $realtimeBefore = CarbonImmutable::now()->subHours(max(1, $realtimeRetentionHours));

        $deletedRecipients = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->whereNotNull('read_at')
            ->where('read_at', '<', $readBefore)
            ->delete();

        $orphanNotificationIds = DB::table(NotificationsDatabaseTable::NOTIFICATIONS)
            ->leftJoin(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS, 'notifications.id', '=', 'notification_recipients.notification_id')
            ->whereNull('notification_recipients.id')
            ->pluck('notifications.id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $deletedNotifications = $orphanNotificationIds === []
            ? 0
            : DB::table(NotificationsDatabaseTable::NOTIFICATIONS)->whereIn('id', $orphanNotificationIds)->delete();

        $deletedRealtimeEvents = DB::table(NotificationsDatabaseTable::REALTIME_EVENTS)
            ->where('created_at', '<', $realtimeBefore)
            ->delete();

        return new NotificationCleanupResult(
            deletedRecipients: (int) $deletedRecipients,
            deletedNotifications: (int) $deletedNotifications,
            deletedRealtimeEvents: (int) $deletedRealtimeEvents,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedPayload(mixed $value): array
    {
        $payload = json_decode($this->scalarString($value), true);

        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        foreach ($payload as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, scalar|null>  $payload
     */
    private function insertRealtimeEvent(string $topic, string $eventType, ?int $userId, ?int $teamId, array $payload): string
    {
        $publicId = (string) Str::ulid();

        DB::table(NotificationsDatabaseTable::REALTIME_EVENTS)->insert([
            'public_id' => $publicId,
            'topic' => $topic,
            'event_type' => $eventType,
            'user_id' => $userId,
            'team_id' => $teamId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    /**
     * @return list<NotificationSummary>
     */
    private function summaries(string $userPublicId, ?string $teamPublicId, ?int $limit): array
    {
        $userId = $this->userId($userPublicId);

        if (! is_int($userId)) {
            return [];
        }

        $query = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(NotificationsDatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notification_recipients.user_id', $userId)
            ->orderByDesc('notifications.created_at')
            ->select([
                'notifications.public_id',
                'notifications.type',
                'notifications.severity',
                'notifications.title',
                'notifications.body',
                'notifications.deep_link_url',
                'notifications.data',
                'notification_recipients.team_id',
                'notification_recipients.read_at',
                'notifications.created_at',
            ]);

        $teamId = $teamPublicId === null ? null : $this->teamId($teamPublicId);

        if (is_int($teamId)) {
            $query->where(static function (Builder $query) use ($teamId): void {
                $query->whereNull('notification_recipients.team_id')->orWhere('notification_recipients.team_id', $teamId);
            });
        }

        if (is_int($limit)) {
            $query->limit($limit);
        }

        $rows = [];

        foreach ($query->get() as $row) {
            $values = get_object_vars($row);
            $readAt = $values['read_at'] ?? null;

            $rows[] = new NotificationSummary(
                publicId: $this->scalarString($values['public_id'] ?? ''),
                type: $this->scalarString($values['type'] ?? ''),
                severity: $this->scalarString($values['severity'] ?? 'info'),
                title: $this->scalarString($values['title'] ?? ''),
                body: is_string($values['body'] ?? null) ? $values['body'] : null,
                deepLinkUrl: is_string($values['deep_link_url'] ?? null) ? $values['deep_link_url'] : null,
                teamPublicId: is_numeric($values['team_id'] ?? null) ? $this->teams->publicIdForInternalId((int) $values['team_id']) : null,
                read: $readAt !== null,
                createdAt: $this->dateTimeString($values['created_at'] ?? null),
                readAt: $this->nullableDateTimeString($readAt),
                data: $this->jsonScalarMap($values['data'] ?? null),
            );
        }

        return $rows;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function jsonScalarMap(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key) && (is_scalar($item) || $item === null)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function userId(string $userPublicId): ?int
    {
        return $this->users->internalIdForPublicId($userPublicId);
    }

    private function teamId(string $teamPublicId): ?int
    {
        return $this->teams->internalIdForPublicId($teamPublicId);
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableDateTimeString(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (! $value instanceof DateTimeInterface) {
            return null;
        }

        return $value->format(DATE_ATOM);
    }

    private function dateTimeString(mixed $value): string
    {
        return $this->nullableDateTimeString($value) ?? '';
    }
}
