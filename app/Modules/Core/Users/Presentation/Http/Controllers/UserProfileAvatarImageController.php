<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Application\Public\Exceptions\FileNotAvailableForDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class UserProfileAvatarImageController
{
    public function __construct(private FileStorage $files) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filePublicId = data_get($user, 'avatar_image_file_public_id');

        if (! is_string($filePublicId) || $filePublicId === '') {
            abort(404);
        }

        try {
            $download = $this->files->cleanDownloadFile($filePublicId, $this->intValue(data_get($user, 'id')), null);
        } catch (FileNotAvailableForDownload) {
            abort(404);
        }

        return Storage::disk($download->disk)->response($download->path, $download->filename, [
            'Content-Type' => $download->mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }
}
