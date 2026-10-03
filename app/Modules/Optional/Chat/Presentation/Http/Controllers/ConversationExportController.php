<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\Exports\ConversationExportProvider;
use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\Exports\DTOs\AuthorizedReportExportRequest;
use App\Shared\Application\Exports\Enums\ReportExportFormat;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Presentation\Support\FlashMessage;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class ConversationExportController
{
    private const COLUMNS = ['message_public_id', 'author', 'body', 'created_at', 'edited', 'edit_history', 'forwarded', 'attachments'];

    public function __construct(private ChatModuleAccess $access, private ConversationManager $conversations, private ReportExportGenerationDispatcher $exports, private TeamLookup $teams) {}

    public function __invoke(Request $request, string $conversation): RedirectResponse
    {
        $request->validate(['format' => ['required', Rule::in(['csv', 'json', 'pdf'])]]);
        $actorId = data_get($request->user(), 'id');
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->session()->get('active_team_public_id');
        abort_unless(is_numeric($actorId) && is_string($actorPublicId) && is_string($teamPublicId), 403);
        $this->access->ensureAllowed($actorPublicId, $teamPublicId, ChatPermissionCatalog::EXPORT_STORE);
        abort_unless($this->conversations->canAccess($actorPublicId, $teamPublicId, $conversation), 403);
        $format = ReportExportFormat::from($request->string('format')->toString());
        $export = new AuthorizedReportExportRequest(
            reportKey: ConversationExportProvider::KEY, reportName: __('exports.chat.name'), moduleKey: 'chat', format: $format,
            activeTeamId: $this->teams->internalIdForPublicId($teamPublicId), activeTeamPublicId: $teamPublicId,
            requestingUserId: (int) $actorId, requestingUserPublicId: $actorPublicId,
            filters: ['conversation_public_id' => $conversation], sorting: [['id' => 'created_at', 'desc' => false]], columns: self::COLUMNS,
            permissionNames: [ReportsPermissionCatalog::REQUEST, ChatPermissionCatalog::EXPORT_STORE],
            releaseVersion: 'phase-31', ruleVersion: 'chat-conversation-export-v1', expiresAt: new DateTimeImmutable('+7 days'), locale: app()->getLocale(),
        );
        $result = $this->exports->dispatchAuthorized($export);

        return back()->with('flash.messages', [FlashMessage::success('flash.exports.queued')])
            ->with('export_request_public_id', $result->exportRequestPublicId)
            ->with('export_artifact_public_id', $result->artifactPublicId);
    }
}
