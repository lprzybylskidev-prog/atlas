<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;
use App\Modules\Optional\Chat\Application\MeetingRecordingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final readonly class MeetingRecordingController
{
    public function __construct(private MeetingRecordingManager $recordings) {}

    public function show(Request $request, string $meeting): JsonResponse
    {
        return $this->run($request, $meeting, fn (string $u, string $t, string $d): array => $this->recordings->state($u, $t, $meeting, $d));
    }

    public function control(Request $request, string $meeting, string $action): JsonResponse
    {
        if (! in_array($action, ['start', 'pause', 'resume', 'stop'], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $this->run($request, $meeting, fn (string $u, string $t, string $d): array => $this->recordings->control($u, $t, $meeting, $d, $action));
    }

    /** @param callable(string, string, string): array<string, scalar|null> $operation */
    private function run(Request $request, string $meeting, callable $operation): JsonResponse
    {
        $request->validate(['occurrence_date' => ['required', 'date_format:Y-m-d']]);
        $user = data_get($request->user(), 'public_id');
        $team = $request->session()->get('active_team_public_id');
        if (! is_string($user) || ! is_string($team)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        try {
            return response()->json(['recording' => $operation($user, $team, $request->string('occurrence_date')->toString())]);
        } catch (MeetingOperationDenied|LogicException $exception) {
            return response()->json(['code' => 'meeting_recording_denied'], Response::HTTP_FORBIDDEN);
        } catch (RtcUnavailable $exception) {
            return response()->json(['code' => 'meeting_recording_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
