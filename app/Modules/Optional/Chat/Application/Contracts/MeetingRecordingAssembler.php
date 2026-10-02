<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

interface MeetingRecordingAssembler
{
    /** @param list<string> $segmentPaths */
    public function segmentsReady(array $segmentPaths): bool;

    /** @param list<string> $segmentPaths */
    public function assemble(array $segmentPaths, string $recordingPublicId): string;

    /** @param list<string> $segmentPaths */
    public function cleanup(array $segmentPaths, string $finalPath): void;
}
