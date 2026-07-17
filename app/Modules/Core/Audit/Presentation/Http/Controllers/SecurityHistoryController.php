<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Http\Controllers;

use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SecurityHistoryController
{
    public function __invoke(Request $request): Response
    {
        $userPublicId = $this->filterString($request, 'user');
        $query = DB::table(DatabaseTable::AUDIT_EVENTS)
            ->where('is_security', true);

        if ($userPublicId !== '') {
            $query->where(static function (Builder $query) use ($userPublicId): void {
                $query
                    ->where('actor_public_id', $userPublicId)
                    ->orWhere('actual_actor_public_id', $userPublicId)
                    ->orWhere('impersonated_user_public_id', $userPublicId)
                    ->orWhere('target_public_id', $userPublicId);
            });
        }

        $records = array_values($query
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get()
            ->all());
        $users = $this->usersForEvents($records);

        $events = array_map(fn (object $record): array => [
            'publicId' => self::stringValue($record, 'public_id'),
            'occurredAt' => self::stringValue($record, 'occurred_at'),
            'user' => $this->eventUser($record, $users),
            'module' => self::stringValue($record, 'module'),
            'action' => self::stringValue($record, 'action'),
            'result' => self::stringValue($record, 'result'),
            'source' => self::stringValue($record, 'source'),
            'actorPublicId' => self::stringValue($record, 'actor_public_id'),
            'actualActorPublicId' => self::stringValue($record, 'actual_actor_public_id'),
            'impersonatedUserPublicId' => self::stringValue($record, 'impersonated_user_public_id'),
            'impersonationSessionId' => self::stringValue($record, 'impersonation_session_id'),
            'targetType' => self::stringValue($record, 'target_type'),
            'targetPublicId' => self::stringValue($record, 'target_public_id'),
            'teamPublicId' => self::stringValue($record, 'team_public_id'),
            'reason' => self::stringValue($record, 'reason'),
        ], $records);

        return Inertia::render('Admin/Audit/SecurityHistory', [
            'events' => $events,
            'filters' => [
                'userPublicId' => $userPublicId,
            ],
            'userOptions' => $this->userOptions(),
        ]);
    }

    /**
     * @param  list<object>  $records
     * @return array<string, array{name: string, email: string}>
     */
    private function usersForEvents(array $records): array
    {
        $publicIds = [];

        foreach ($records as $record) {
            foreach (['impersonated_user_public_id', 'target_public_id', 'actor_public_id', 'actual_actor_public_id'] as $property) {
                $publicId = self::stringValue($record, $property);

                if ($publicId !== '') {
                    $publicIds[$publicId] = true;
                }
            }
        }

        if ($publicIds === []) {
            return [];
        }

        $users = [];

        foreach (DB::table(DatabaseTable::USERS)
            ->whereIn('public_id', array_keys($publicIds))
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = self::stringValue($user, 'public_id');

            if ($publicId === '') {
                continue;
            }

            $users[$publicId] = [
                'name' => self::stringValue($user, 'name'),
                'email' => self::stringValue($user, 'email'),
            ];
        }

        return $users;
    }

    /**
     * @param  array<string, array{name: string, email: string}>  $users
     * @return array{publicId: string, name: string, email: string, context: string}
     */
    private function eventUser(object $record, array $users): array
    {
        foreach ([
            'impersonated_user_public_id' => 'Impersonated user',
            'target_public_id' => 'Target user',
            'actor_public_id' => 'Actor',
            'actual_actor_public_id' => 'Actual actor',
        ] as $property => $context) {
            $publicId = self::stringValue($record, $property);

            if ($publicId === '') {
                continue;
            }

            $user = $users[$publicId] ?? null;

            return [
                'publicId' => $publicId,
                'name' => $user === null ? $publicId : $user['name'],
                'email' => $user === null ? '' : $user['email'],
                'context' => $context,
            ];
        }

        return [
            'publicId' => '',
            'name' => '',
            'email' => '',
            'context' => '',
        ];
    }

    private function filterString(Request $request, string $key): string
    {
        $value = preg_replace('/[[:cntrl:]]/', '', (string) $request->query($key, '')) ?? '';

        return mb_substr(trim($value), 0, 120);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function userOptions(): array
    {
        $options = [];

        foreach (DB::table(DatabaseTable::USERS)
            ->orderBy('name')
            ->orderBy('email')
            ->get(['public_id', 'name', 'email'])
            ->all() as $user) {
            $publicId = self::stringValue($user, 'public_id');

            if ($publicId === '') {
                continue;
            }

            $name = self::stringValue($user, 'name');
            $email = self::stringValue($user, 'email');
            $label = trim($name) !== '' ? $name : $publicId;

            if (trim($email) !== '') {
                $label = sprintf('%s <%s>', $label, $email);
            }

            $options[] = [
                'value' => $publicId,
                'label' => sprintf('%s - %s', $label, $publicId),
            ];
        }

        return $options;
    }

    private static function stringValue(object $record, string $property): string
    {
        $value = $record->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
