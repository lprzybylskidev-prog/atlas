<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Http\Controllers;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;
use App\Modules\Core\Notifications\Presentation\Support\NotificationTextLocalizer;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationCenterController
{
    public function __construct(
        private NotificationInbox $notifications,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::NOTIFICATIONS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $localizer = new NotificationTextLocalizer;

        $allRows = is_string($userPublicId)
            ? array_map(
                static fn (NotificationSummary $notification): array => [
                    'publicId' => $notification->publicId,
                    'type' => $notification->type,
                    'severity' => $notification->severity,
                    'title' => $localizer->title($notification),
                    'body' => $localizer->body($notification) ?? '',
                    'teamPublicId' => $notification->teamPublicId ?? '',
                    'scope' => $notification->teamPublicId === null ? 'global' : 'team',
                    'scopeLabel' => $notification->teamPublicId === null ? __('notifications.scope.global') : __('notifications.scope.team'),
                    'read' => $notification->read,
                    'createdAt' => $notification->createdAt,
                    'readAt' => $notification->readAt ?? '',
                    'deepLinkUrl' => $notification->deepLinkUrl ?? '',
                ],
                $this->notifications->allForUser($userPublicId, is_string($teamPublicId) ? $teamPublicId : null),
            )
            : [];
        $filters = $this->filters($request, $allRows);
        $rows = $this->filteredRows($allRows, $filters);

        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key);
        $table['state']['filters'] = $filters;

        return Inertia::render('Notifications/Index', [
            'notificationRows' => $result->rows,
            'summary' => $this->summary($allRows, $rows),
            'filterOptions' => [
                'severities' => $this->uniqueValues($allRows, 'severity'),
                'types' => $this->uniqueValues($allRows, 'type'),
            ],
            'table' => $table,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{status: string, severity: string, scope: string, type: string, link: string, from: string, to: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'status' => $this->oneOf($request->query('status'), ['all', 'unread', 'read']),
            'severity' => $this->oneOf($request->query('severity'), $this->allOr($this->uniqueValues($rows, 'severity'))),
            'scope' => $this->oneOf($request->query('scope'), ['all', 'global', 'team']),
            'type' => $this->oneOf($request->query('type'), $this->allOr($this->uniqueValues($rows, 'type'))),
            'link' => $this->oneOf($request->query('link'), ['all', 'with_link', 'without_link']),
            'from' => $this->dateValue($request->query('from')),
            'to' => $this->dateValue($request->query('to')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{status: string, severity: string, scope: string, type: string, link: string, from: string, to: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'unread' && ($row['read'] ?? false) !== false) {
                return false;
            }

            if ($filters['status'] === 'read' && ($row['read'] ?? false) !== true) {
                return false;
            }

            foreach (['severity', 'scope', 'type'] as $key) {
                if ($filters[$key] !== 'all' && ($row[$key] ?? '') !== $filters[$key]) {
                    return false;
                }
            }

            $hasLink = trim(self::stringValue($row['deepLinkUrl'] ?? '')) !== '';

            if ($filters['link'] === 'with_link' && ! $hasLink) {
                return false;
            }

            if ($filters['link'] === 'without_link' && $hasLink) {
                return false;
            }

            return self::dateRangeMatches(self::stringValue($row['createdAt'] ?? ''), $filters['from'], $filters['to']);
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $allRows
     * @param  list<array<string, mixed>>  $visibleRows
     * @return array{total: int, visible: int, unread: int, read: int, warnings: int, danger: int, withLinks: int}
     */
    private function summary(array $allRows, array $visibleRows): array
    {
        return [
            'total' => count($allRows),
            'visible' => count($visibleRows),
            'unread' => count(array_filter($allRows, static fn (array $row): bool => ($row['read'] ?? false) === false)),
            'read' => count(array_filter($allRows, static fn (array $row): bool => ($row['read'] ?? false) === true)),
            'warnings' => count(array_filter($allRows, static fn (array $row): bool => ($row['severity'] ?? '') === 'warning')),
            'danger' => count(array_filter($allRows, static fn (array $row): bool => in_array($row['severity'] ?? '', ['critical', 'error'], true))),
            'withLinks' => count(array_filter($allRows, static fn (array $row): bool => trim(self::stringValue($row['deepLinkUrl'] ?? '')) !== '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? '';

            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return array_values(array_unique(['all', ...$values]));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        return 'all';
    }

    private function dateValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = mb_substr((string) $value, 0, 20);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function dateRangeMatches(string $value, string $from, string $to): bool
    {
        if ($value === '') {
            return $from === '' && $to === '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return true;
        }

        if ($from !== '') {
            $fromTimestamp = strtotime($from.' 00:00:00');

            if ($fromTimestamp !== false && $timestamp < $fromTimestamp) {
                return false;
            }
        }

        if ($to !== '') {
            $toTimestamp = strtotime($to.' 23:59:59');

            if ($toTimestamp !== false && $timestamp > $toTimestamp) {
                return false;
            }
        }

        return true;
    }
}
