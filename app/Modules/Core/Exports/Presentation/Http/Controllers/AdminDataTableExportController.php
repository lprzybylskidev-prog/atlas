<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Presentation\Http\Controllers;

use App\Modules\Core\Exports\Application\AdminDataTableExportProviderRegistry;
use App\Modules\Core\Exports\Application\AdminDataTableExportSnapshotFactory;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Core\Exports\Application\Public\DTOs\AdminDataTableExportContext;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final readonly class AdminDataTableExportController
{
    public function __construct(
        private AdminDataTableExportProviderRegistry $providers,
        private AdminDataTableExportSnapshotFactory $snapshots,
        private ReportExportGenerationDispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'table_key' => ['required', 'string', 'max:120'],
            'format' => ['required', Rule::in(array_map(static fn (ReportExportFormat $format): string => $format->value, ReportExportFormat::cases()))],
            'page' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer'],
            'sort' => ['sometimes', 'string', 'max:120'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'columns' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'column_order' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'view' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $actor = $request->user();
        $actorId = data_get($actor, 'id');
        $actorPublicId = data_get($actor, 'public_id');

        abort_unless(is_numeric($actorId) && is_string($actorPublicId), 403);

        try {
            $tableKey = $request->string('table_key')->toString();
            $format = ReportExportFormat::from($request->string('format', ReportExportFormat::Csv->value)->toString());
            $provider = $this->providers->get($tableKey);
            $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
            $teamId = is_string($teamPublicId) ? DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id') : null;
            $state = TableState::fromPayload($this->payload($request), $provider->tableDefinition());
            $context = new AdminDataTableExportContext(
                state: $state,
                requestingUserId: (int) $actorId,
                requestingUserPublicId: $actorPublicId,
                activeTeamId: is_numeric($teamId) ? (int) $teamId : null,
                activeTeamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
                filters: $this->filters($request),
                timeRange: null,
                estimatedRowCount: null,
                expiresAt: new DateTimeImmutable('+7 days'),
            );

            abort_unless(in_array($format, $provider->supportedFormats($context), true), 422);

            $snapshot = $this->snapshots->snapshot(
                provider: $provider,
                context: $context,
                format: $format,
            );
            $result = $this->dispatcher->dispatchSnapshot($snapshot);
        } catch (RuntimeException) {
            return back()->with('flash.messages', [
                FlashMessage::error('flash.exports.queue_failed'),
            ]);
        }

        if ($format === ReportExportFormat::BrowserPrint) {
            return redirect()->route('exports.print', ['export' => $result->exportRequestPublicId]);
        }

        return back()
            ->with('flash.messages', [
                FlashMessage::success('flash.exports.queued'),
            ])
            ->with('export_request_public_id', $result->exportRequestPublicId)
            ->with('export_execution_mode', $result->executionMode)
            ->with('export_artifact_public_id', $result->artifactPublicId)
            ->with('export_process_run_public_id', $result->processRunPublicId);
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function filters(Request $request): array
    {
        $reserved = ['table_key', 'format', 'page', 'per_page', 'sort', 'direction', 'search', 'columns', 'column_order', 'view', '_token'];
        $filters = [];

        foreach ($request->all() as $key => $value) {
            if (! is_string($key) || in_array($key, $reserved, true)) {
                continue;
            }

            if (is_string($value) || is_int($value) || is_bool($value) || $value === null) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = [];

        foreach ($request->all() as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
