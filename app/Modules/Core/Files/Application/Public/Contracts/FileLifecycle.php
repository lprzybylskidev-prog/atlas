<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

use App\Modules\Core\Files\Application\Public\DTOs\FileLifecycleResult;
use Illuminate\Http\UploadedFile;

interface FileLifecycle
{
    public function replace(string $publicId, UploadedFile $replacement, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult;

    public function delete(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult;

    public function anonymize(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult;

    public function createRetentionCopy(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult;

    public function createRetentionExport(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult;
}
