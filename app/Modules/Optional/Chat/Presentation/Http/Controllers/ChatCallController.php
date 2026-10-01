<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\CallManager;
use App\Modules\Optional\Chat\Application\DTOs\CallPreferences;
use App\Modules\Optional\Chat\Application\DTOs\CallSnapshot;
use App\Modules\Optional\Chat\Application\Exceptions\RtcUnavailable;
use App\Modules\Optional\Chat\Application\RtcAccessManager;
use App\Modules\Optional\Chat\Domain\Calls\Exceptions\CallOperationDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ChatCallController
{
    public function __construct(
        private CallManager $calls,
        private RtcAccessManager $rtc,
    ) {}

    public function start(Request $request, string $conversation): JsonResponse
    {
        $request->validate([
            'camera_enabled' => ['required', 'boolean'],
            'client_request_key' => ['required', 'string', 'max:120'],
        ]);
        $clientRequestKey = $request->input('client_request_key');

        if (! is_string($clientRequestKey)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            $result = $this->calls->start(
                $userPublicId,
                $teamPublicId,
                $conversation,
                $request->boolean('camera_enabled'),
                $clientRequestKey,
            );
            $rtc = null;

            if ($result->call->status->acceptsParticipants() && $result->call->currentUserState->value === 'joined') {
                $rtc = $this->rtc->issueParticipantAccess($result->call->publicId, $userPublicId, $teamPublicId);
            }

            return response()->json([
                'call' => $this->snapshot($result->call),
                'created' => $result->created,
                'rtc' => $rtc,
            ], $result->created ? Response::HTTP_CREATED : Response::HTTP_OK);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        } catch (RtcUnavailable) {
            if (isset($result)) {
                $this->calls->markJoinFailed($userPublicId, $result->call->publicId);
            }

            return response()->json(['code' => 'rtc_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    public function current(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $call = $this->calls->current($userPublicId, $teamPublicId);

        return response()->json(['call' => $call === null ? null : $this->snapshot($call)]);
    }

    public function join(Request $request, string $call): JsonResponse
    {
        $request->validate([
            'camera_enabled' => ['required', 'boolean'],
            'microphone_enabled' => ['required', 'boolean'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            $snapshot = $this->calls->join(
                $userPublicId,
                $teamPublicId,
                $call,
                $request->boolean('camera_enabled'),
                $request->boolean('microphone_enabled'),
            );
            $rtc = $this->rtc->issueParticipantAccess($call, $userPublicId, $teamPublicId);

            return response()->json(['call' => $this->snapshot($snapshot), 'rtc' => $rtc]);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        } catch (RtcUnavailable) {
            $this->calls->markJoinFailed($userPublicId, $call);

            return response()->json(['code' => 'rtc_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            $this->calls->markJoinFailed($userPublicId, $call);

            throw $exception;
        }
    }

    public function decline(Request $request, string $call): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            return response()->json(['call' => $this->snapshot($this->calls->decline($userPublicId, $teamPublicId, $call))]);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        }
    }

    public function leave(Request $request, string $call): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            $snapshot = $this->calls->leave($userPublicId, $teamPublicId, $call);

            return response()->json(['call' => $this->snapshot($snapshot)]);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        }
    }

    public function media(Request $request, string $call): JsonResponse
    {
        $request->validate([
            'camera_enabled' => ['required', 'boolean'],
            'microphone_enabled' => ['required', 'boolean'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            $snapshot = $this->calls->updateMedia(
                $userPublicId,
                $teamPublicId,
                $call,
                $request->boolean('camera_enabled'),
                $request->boolean('microphone_enabled'),
            );

            return response()->json(['call' => $this->snapshot($snapshot)]);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        }
    }

    public function startScreenShare(Request $request, string $call): JsonResponse
    {
        return $this->screenShare($request, $call, true);
    }

    public function stopScreenShare(Request $request, string $call): JsonResponse
    {
        return $this->screenShare($request, $call, false);
    }

    public function preferences(Request $request): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json(['preferences' => $this->calls->preferences($userPublicId, $teamPublicId)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'camera_device_id' => ['nullable', 'string', 'max:512'],
            'microphone_device_id' => ['nullable', 'string', 'max:512'],
            'speaker_device_id' => ['nullable', 'string', 'max:512'],
            'outgoing_camera_enabled' => ['required', 'boolean'],
        ]);
        [$userPublicId, $teamPublicId] = $this->context($request);
        $preferences = $this->calls->savePreferences($userPublicId, $teamPublicId, new CallPreferences(
            cameraDeviceId: $this->nullableString($request->input('camera_device_id')),
            microphoneDeviceId: $this->nullableString($request->input('microphone_device_id')),
            speakerDeviceId: $this->nullableString($request->input('speaker_device_id')),
            outgoingCameraEnabled: $request->boolean('outgoing_camera_enabled'),
        ));

        return response()->json(['preferences' => $preferences]);
    }

    private function screenShare(Request $request, string $call, bool $active): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        try {
            return response()->json(['call' => $this->snapshot($this->calls->setScreenShare($userPublicId, $teamPublicId, $call, $active))]);
        } catch (CallOperationDenied $exception) {
            return $this->denied($exception);
        }
    }

    /** @return array{string, string} */
    private function context(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return [$userPublicId, $teamPublicId];
    }

    /** @return array<string, mixed> */
    private function snapshot(CallSnapshot $call): array
    {
        return [
            'publicId' => $call->publicId,
            'conversationPublicId' => $call->conversationPublicId,
            'conversationType' => $call->conversationType->value,
            'conversationLabel' => $call->conversationLabel,
            'startedByUserPublicId' => $call->startedByUserPublicId,
            'startedByName' => $call->startedByName,
            'initialCameraEnabled' => $call->initialCameraEnabled,
            'status' => $call->status->value,
            'currentUserState' => $call->currentUserState->value,
            'incoming' => $call->incoming,
            'teamJoinStyle' => $call->teamJoinStyle,
            'canRejoin' => $call->canRejoin,
            'startedAt' => $call->startedAt,
            'answeredAt' => $call->answeredAt,
            'endedAt' => $call->endedAt,
            'participants' => $call->participants,
        ];
    }

    private function denied(CallOperationDenied $exception): JsonResponse
    {
        $status = match ($exception->reason) {
            'not_found' => Response::HTTP_NOT_FOUND,
            'not_participant' => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_CONFLICT,
        };

        return response()->json(['code' => $exception->reason], $status);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
