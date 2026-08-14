<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Http\Controllers;

use App\Modules\Core\Files\Application\Public\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Exceptions\FileNotAvailableForDownload;
use App\Modules\Optional\Chat\Application\AttachmentManager;
use App\Modules\Optional\Chat\Application\DTOs\MessageAttachment;
use App\Modules\Optional\Chat\Domain\Messages\Exceptions\MessageOperationDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ChatAttachmentController
{
    private const PREVIEW_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm', 'audio/mp4',
        'video/mp4', 'video/webm',
    ];

    public function __construct(private AttachmentManager $attachments) {}

    public function store(Request $request, string $conversation): JsonResponse
    {
        $values = $request->validate(['file' => ['required', 'file']]);
        $file = is_array($values) ? ($values['file'] ?? null) : null;

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => __('validation.uploaded')]);
        }

        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json($this->payload($this->attachments->upload($userPublicId, $teamPublicId, $conversation, $file)), 201);
    }

    public function storeVoice(Request $request, string $conversation): JsonResponse
    {
        $values = $request->validate([
            'file' => ['required', 'file'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:900'],
        ]);
        $file = is_array($values) ? ($values['file'] ?? null) : null;
        $durationSeconds = is_array($values) ? ($values['duration_seconds'] ?? null) : null;

        if (! $file instanceof UploadedFile || ! is_numeric($durationSeconds)) {
            throw ValidationException::withMessages(['file' => __('validation.uploaded')]);
        }

        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json($this->payload($this->attachments->uploadVoice($userPublicId, $teamPublicId, $conversation, $file, (int) $durationSeconds)), 201);
    }

    public function show(Request $request, string $conversation, string $attachment): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json($this->payload($this->attachments->status($userPublicId, $teamPublicId, $conversation, $attachment)));
    }

    public function retry(Request $request, string $conversation, string $attachment): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);

        return response()->json($this->payload($this->attachments->retryScan($userPublicId, $teamPublicId, $conversation, $attachment)));
    }

    public function destroy(Request $request, string $conversation, string $attachment): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $this->attachments->discard($userPublicId, $teamPublicId, $conversation, $attachment);

        return response()->json([], 204);
    }

    public function content(Request $request, string $conversation): JsonResponse
    {
        [$userPublicId, $teamPublicId] = $this->context($request);
        $content = $this->attachments->content($userPublicId, $teamPublicId, $conversation);

        return response()->json([
            'media' => array_map($this->payload(...), $content->media),
            'files' => array_map($this->payload(...), $content->files),
            'links' => $content->links,
        ]);
    }

    public function download(Request $request, string $conversation, string $attachment): StreamedResponse
    {
        try {
            [$userPublicId, $teamPublicId] = $this->context($request);
            $download = $this->attachments->downloadable($userPublicId, $teamPublicId, $conversation, $attachment);
        } catch (FileNotAvailableForDownload|MessageOperationDenied) {
            abort(404);
        }

        $preview = $request->boolean('preview') && in_array($download->mimeType, self::PREVIEW_MIME_TYPES, true);
        $headers = [
            'Content-Type' => $download->mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; media-src 'self'; img-src 'self' data:",
        ];

        return $preview
            ? Storage::disk($download->disk)->response($download->path, $download->filename, $headers)
            : Storage::disk($download->disk)->download($download->path, $download->filename, $headers);
    }

    /** @return array{string, string} */
    private function context(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            abort(403);
        }

        return [$userPublicId, $teamPublicId];
    }

    /** @return array<string, bool|int|string|null> */
    private function payload(MessageAttachment $attachment): array
    {
        return [
            'publicId' => $attachment->publicId,
            'kind' => $attachment->kind->value,
            'name' => $attachment->originalName,
            'mimeType' => $attachment->mimeType,
            'sizeBytes' => $attachment->sizeBytes,
            'durationSeconds' => $attachment->durationSeconds,
            'scanState' => $attachment->scanState->value,
            'available' => $attachment->available(),
            'previewable' => $attachment->scanState === FileScanState::Clean && in_array($attachment->mimeType, self::PREVIEW_MIME_TYPES, true),
            'attached' => $attachment->messageId !== null,
        ];
    }
}
