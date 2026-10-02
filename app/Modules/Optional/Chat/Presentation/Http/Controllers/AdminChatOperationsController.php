<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

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
    public function __construct(private MeetingRecordingRetention $retention, private ManagedProcessRunner $processes) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Chat/Operations', ['recordingRetention' => $this->retention->summary()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['recording_retention_days' => ['nullable', 'integer', 'between:1,3650']]);
        $actor = data_get($request->user(), 'public_id');
        $team = $request->session()->get('active_team_public_id');
        abort_unless(is_string($actor) && is_string($team), 403);
        $this->retention->configure(
            $request->filled('recording_retention_days') ? $request->integer('recording_retention_days') : null,
            $actor,
            $team,
        );

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.recording_retention_updated')]);
    }

    public function run(Request $request): RedirectResponse
    {
        $actor = data_get($request->user(), 'public_id');
        $team = $request->session()->get('active_team_public_id');
        abort_unless(is_string($actor) && is_string($team), 403);
        $this->processes->start(MeetingRecordingRetentionProcess::KEY, 'manual', null, $actor, $team);

        return back()->with('flash.messages', [FlashMessage::success('flash.chat.recording_retention_queued')]);
    }
}
