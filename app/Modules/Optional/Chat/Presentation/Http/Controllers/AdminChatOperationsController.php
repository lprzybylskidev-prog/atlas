<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\ChatOperationsSummary;
use App\Modules\Optional\Chat\Application\ChatRetention;
use App\Modules\Optional\Chat\Application\ChatRetentionProcess;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetentionProcess;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminChatOperationsController
{
    public function __construct(
        private ChatRetention $chatRetention,
        private MeetingRecordingRetention $recordingRetention,
        private ChatOperationsSummary $operations,
        private ManagedProcessRunner $processes,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Chat/Operations', $this->operations->get());
    }

    public function updateChatRetention(Request $request): RedirectResponse
    {
        $request->validate(['retention_days' => ['nullable', 'integer', 'between:1,3650']]);
        [$actor, $team] = $this->context($request);
        $this->chatRetention->configure($request->filled('retention_days') ? $request->integer('retention_days') : null, $actor, $team);

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.retention_updated')]);
    }

    public function runChatRetention(Request $request): RedirectResponse
    {
        [$actor, $team] = $this->context($request);
        $processPublicId = $this->processes->start(ChatRetentionProcess::KEY, 'manual', null, $actor, $team);
        $this->chatRetention->auditRunRequested($actor, $team, $processPublicId);

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.retention_queued')]);
    }

    public function updateRecordingRetention(Request $request): RedirectResponse
    {
        $request->validate(['recording_retention_days' => ['nullable', 'integer', 'between:1,3650']]);
        [$actor, $team] = $this->context($request);
        $this->recordingRetention->configure(
            $request->filled('recording_retention_days') ? $request->integer('recording_retention_days') : null,
            $actor,
            $team,
        );

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.recording_retention_updated')]);
    }

    public function runRecordingRetention(Request $request): RedirectResponse
    {
        [$actor, $team] = $this->context($request);
        $processPublicId = $this->processes->start(MeetingRecordingRetentionProcess::KEY, 'manual', null, $actor, $team);
        $this->recordingRetention->auditRunRequested($actor, $team, $processPublicId);

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.recording_retention_queued')]);
    }

    /** @return array{string,string} */
    private function context(Request $request): array
    {
        $actor = data_get($request->user(), 'public_id');
        $team = $request->session()->get('active_team_public_id');
        abort_unless(is_string($actor) && is_string($team), 403);

        return [$actor, $team];
    }
}
