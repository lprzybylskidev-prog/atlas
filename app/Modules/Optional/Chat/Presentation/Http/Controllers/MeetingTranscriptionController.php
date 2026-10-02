<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Optional\Chat\Application\TranscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class MeetingTranscriptionController
{
    public function __construct(private TranscriptionManager $transcriptions) {}

    public function store(Request $request, string $recording): JsonResponse
    {
        [$user, $team] = $this->context($request);

        return response()->json(['transcription' => $this->transcriptions->request($user, $team, $recording)], 202);
    }

    public function show(Request $request, string $transcription): JsonResponse
    {
        [$user, $team] = $this->context($request);

        return response()->json(['transcription' => $this->transcriptions->view($user, $team, $transcription)]);
    }

    public function update(Request $request, string $transcription): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:2000000'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        [$user, $team] = $this->context($request);

        return response()->json(['transcription' => $this->transcriptions->edit(
            $user,
            $team,
            $transcription,
            $request->integer('expected_version'),
            $request->string('text')->toString(),
        )]);
    }

    public function share(Request $request, string $transcription): JsonResponse
    {
        $request->validate(['recipient_public_id' => ['required', 'string', 'size:26']]);
        [$user, $team] = $this->context($request);

        return response()->json(['share' => $this->transcriptions->share(
            $user,
            $team,
            $transcription,
            $request->string('recipient_public_id')->toString(),
        )], 201);
    }

    public function revoke(Request $request, string $transcription, string $share): JsonResponse
    {
        [$user, $team] = $this->context($request);
        $this->transcriptions->revoke($user, $team, $transcription, $share);

        return response()->json([], 204);
    }

    /** @return array{string,string} */
    private function context(Request $request): array
    {
        $user = data_get($request->user(), 'public_id');
        $team = $request->session()->get('active_team_public_id');

        return is_string($user) && is_string($team) ? [$user, $team] : abort(403);
    }
}
