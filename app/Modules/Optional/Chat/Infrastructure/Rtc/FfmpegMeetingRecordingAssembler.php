<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Rtc;

use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingAssembler;
use RuntimeException;
use Symfony\Component\Process\Process;

final readonly class FfmpegMeetingRecordingAssembler implements MeetingRecordingAssembler
{
    public function __construct(private string $stagingDirectory, private string $ffmpegBinary = '/usr/bin/ffmpeg') {}

    public function segmentsReady(array $segmentPaths): bool
    {
        return $segmentPaths !== [] && array_all($segmentPaths, fn (string $path): bool => is_file($this->absolute($path)) && filesize($this->absolute($path)) > 0);
    }

    public function assemble(array $segmentPaths, string $recordingPublicId): string
    {
        if (! $this->segmentsReady($segmentPaths)) {
            throw new RuntimeException('Meeting recording segments are not ready.');
        }
        $directory = rtrim($this->stagingDirectory, '/').'/meeting-recordings/'.strtolower($recordingPublicId);
        $output = $directory.'/recording.mp4';
        if (count($segmentPaths) === 1) {
            if (! copy($this->absolute($segmentPaths[0]), $output)) {
                throw new RuntimeException('Meeting recording segment could not be finalized.');
            }

            return $output;
        }
        $manifest = $directory.'/segments.txt';
        $lines = array_map(fn (string $path): string => "file '".str_replace("'", "'\\''", $this->absolute($path))."'", $segmentPaths);
        if (file_put_contents($manifest, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Meeting recording segment manifest could not be written.');
        }
        $process = new Process([$this->ffmpegBinary, '-hide_banner', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', $manifest, '-c', 'copy', '-movflags', '+faststart', '-y', $output]);
        $process->setTimeout(3600);
        $process->mustRun();
        @unlink($manifest);

        return $output;
    }

    public function cleanup(array $segmentPaths, string $finalPath): void
    {
        foreach ($segmentPaths as $path) {
            @unlink($this->absolute($path));
        }
        @unlink($finalPath);
        $directory = dirname($finalPath);
        if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
            @rmdir($directory);
        }
    }

    private function absolute(string $relativePath): string
    {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('Unsafe Meeting recording staging path.');
        }

        return rtrim($this->stagingDirectory, '/').'/'.$relativePath;
    }
}
