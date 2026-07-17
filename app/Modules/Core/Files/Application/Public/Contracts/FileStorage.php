<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

use App\Modules\Core\Files\Application\Public\DTOs\StoredFile;
use Illuminate\Http\UploadedFile;

interface FileStorage
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function storeUpload(UploadedFile $file, ?int $actorId = null, ?int $teamId = null, array $metadata = []): StoredFile;

    public function cleanDownloadPath(string $publicId, ?int $actorId = null, ?int $teamId = null): string;
}
