<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Core\Files\Application\Public\Exceptions\FileNotAvailableForDownload;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\MeetingRecordingAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MeetingRecordingAccessController
{
    public function __construct(private MeetingRecordingAccessManager $recordings) {}

    public function show(Request $request, string $recording): JsonResponse
    {
        [$user, $team] = $this->context($request);

        return response()->json(['recording' => $this->recordings->details($user, $team, $recording)]);
    }

    public function download(Request $request, string $recording): StreamedResponse
    {
        try {
            [$user, $team] = $this->context($request);
            $file = $this->recordings->downloadable($user, $team, $recording);
        } catch (MeetingOperationDenied|FileNotAvailableForDownload) {
            abort(404);
        }

        $headers = ['Content-Type' => $file->mimeType, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $request->boolean('preview')
            ? Storage::disk($file->disk)->response($file->path, $file->filename, $headers)
            : Storage::disk($file->disk)->download($file->path, $file->filename, $headers);
    }

    public function share(Request $request, string $recording): JsonResponse
    {
        $request->validate(['recipient_public_id' => ['required', 'string', 'size:26']]);
        [$user, $team] = $this->context($request);
        $recipient = $request->string('recipient_public_id')->toString();

        return response()->json(['share' => $this->recordings->share($user, $team, $recording, $recipient)], 201);
    }

    public function revoke(Request $request, string $recording, string $share): JsonResponse
    {
        [$user, $team] = $this->context($request);
        $this->recordings->revoke($user, $team, $recording, $share);

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
