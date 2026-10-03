<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\DTOs\MeetingRtcSession;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;
use App\Modules\Optional\Chat\Application\MeetingRtcManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class MeetingRtcController
{
    public function __construct(private MeetingRtcManager $sessions) {}

    public function join(Request $request, string $meeting): JsonResponse
    {
        $request->validate(['occurrence_date' => ['required', 'date_format:Y-m-d'], 'camera_enabled' => ['required', 'boolean'], 'microphone_enabled' => ['required', 'boolean']]);

        return $this->run($request, fn (string $u, string $t): array => $this->sessions->join($u, $t, $meeting, $request->string('occurrence_date')->toString(), $request->boolean('camera_enabled'), $request->boolean('microphone_enabled')));
    }

    public function leave(Request $request, string $meeting): JsonResponse
    {
        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->leave($u, $t, $meeting, $d));
    }

    public function media(Request $request, string $meeting): JsonResponse
    {
        $request->validate(['camera_enabled' => ['required', 'boolean'], 'microphone_enabled' => ['required', 'boolean']]);

        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->media($u, $t, $meeting, $d, $request->boolean('camera_enabled'), $request->boolean('microphone_enabled')));
    }

    public function screenShare(Request $request, string $meeting): JsonResponse
    {
        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->screenShare($u, $t, $meeting, $d, $request->isMethod('post')));
    }

    public function moderate(Request $request, string $meeting, string $participant): JsonResponse
    {
        $request->validate(['action' => ['required', 'in:mute,disable_microphone,restore_microphone,camera_off,stop_screen_share,kick']]);

        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->moderate($u, $t, $meeting, $d, $participant, $request->string('action')->toString()));
    }

    public function lock(Request $request, string $meeting): JsonResponse
    {
        $request->validate(['locked' => ['required', 'boolean']]);

        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->lock($u, $t, $meeting, $d, $request->boolean('locked')));
    }

    public function end(Request $request, string $meeting): JsonResponse
    {
        return $this->sessionAction($request, $meeting, fn (string $u, string $t, string $d): MeetingRtcSession => $this->sessions->end($u, $t, $meeting, $d));
    }

    /** @param callable(string, string, string): MeetingRtcSession $action */
    private function sessionAction(Request $request, string $meeting, callable $action): JsonResponse
    {
        $request->validate(['occurrence_date' => ['required', 'date_format:Y-m-d']]);

        return $this->run($request, fn (string $u, string $t): array => ['session' => $action($u, $t, $request->string('occurrence_date')->toString())]);
    }

    /** @param callable(string, string): array{session: MeetingRtcSession, rtc?: RtcParticipantAccess} $action */
    private function run(Request $request, callable $action): JsonResponse
    {
        $u = data_get($request->user(), 'public_id');
        $t = $request->session()->get('active_team_public_id');
        if (! is_string($u) || ! is_string($t)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        try {
            $value = $action($u, $t);
            $value['session'] = $this->session($value['session']);
            if (isset($value['rtc'])) {
                $value['rtc'] = ['serverUrl' => $value['rtc']->serverUrl, 'roomName' => $value['rtc']->roomName, 'participantToken' => $value['rtc']->token, 'expiresAt' => $value['rtc']->expiresAt->format(DATE_ATOM)];
            }

            return response()->json($value);
        } catch (MeetingOperationDenied|\LogicException $e) {
            return response()->json(['code' => 'meeting_rtc_denied'], Response::HTTP_FORBIDDEN);
        } catch (RtcUnavailable) {
            return response()->json(['code' => 'rtc_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /** @return array<string,mixed> */
    private function session(MeetingRtcSession $s): array
    {
        return ['locked' => $s->locked, 'startedAt' => $s->startedAt, 'endedAt' => $s->endedAt, 'emptySince' => $s->emptySince, 'participants' => $s->participants];
    }
}
