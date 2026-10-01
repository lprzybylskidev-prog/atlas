<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\CallManager;
use App\Modules\Optional\Chat\Application\DTOs\CallHistoryEntry;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CallHistoryController
{
    public function __construct(
        private CallManager $calls,
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = RegisteredTables::get(RegisteredTables::CALL_HISTORY);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $filter = $this->filter($request->query('call_filter'));
        $allRows = is_string($userPublicId) && is_string($teamPublicId)
            ? array_map($this->row(...), $this->calls->history($userPublicId, $teamPublicId))
            : [];
        $rows = array_values(array_filter($allRows, static function (array $row) use ($filter): bool {
            return match ($filter) {
                'missed' => ($row['state'] ?? '') === 'missed',
                'incoming' => ($row['direction'] ?? '') === 'incoming',
                'outgoing' => ($row['direction'] ?? '') === 'outgoing',
                default => true,
            };
        }));
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key);
        $table['state']['filters'] = ['call_filter' => $filter];
        $summaryRows = $result->filteredRows;

        return Inertia::render('Calls/History', [
            'callRows' => $result->rows,
            'table' => $table,
            'filter' => $filter,
            'summary' => [
                'total' => count($summaryRows),
                'missed' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['state'] ?? '') === 'missed')),
                'incoming' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['direction'] ?? '') === 'incoming')),
                'outgoing' => count(array_filter($summaryRows, static fn (array $row): bool => ($row['direction'] ?? '') === 'outgoing')),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(CallHistoryEntry $entry): array
    {
        return [
            'publicId' => $entry->publicId,
            'conversationLabel' => $entry->conversationLabel,
            'conversationType' => $entry->conversationType,
            'conversationTypeLabel' => __('calls.conversation_types.'.$entry->conversationType),
            'initialMode' => $entry->initialMode,
            'initialModeLabel' => __('calls.modes.'.$entry->initialMode),
            'direction' => $entry->direction,
            'directionLabel' => __('calls.directions.'.$entry->direction),
            'state' => $entry->state,
            'stateLabel' => __('calls.states.'.$entry->state),
            'startedAt' => $entry->startedAt,
            'duration' => sprintf('%02d:%02d:%02d', intdiv($entry->durationSeconds, 3600), intdiv($entry->durationSeconds % 3600, 60), $entry->durationSeconds % 60),
            'canRejoin' => $entry->canRejoin,
        ];
    }

    private function filter(mixed $value): string
    {
        return is_string($value) && in_array($value, ['all', 'missed', 'incoming', 'outgoing'], true) ? $value : 'all';
    }
}
