<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class OutboxArchitectureTest extends TestCase
{
    public function test_reliable_integration_event_publisher_is_only_used_by_the_outbox_relay(): void
    {
        $basePath = dirname(__DIR__, 3);
        $allowedFiles = [
            $basePath.'/app/Shared/Application/Outbox/Contracts/OutboxEventPublisher.php',
            $basePath.'/app/Shared/Infrastructure/Outbox/DatabaseOutboxRelay.php',
        ];

        foreach ($this->applicationPhpFiles($basePath.'/app') as $file) {
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);

            if (! str_contains($contents, 'OutboxEventPublisher')) {
                continue;
            }

            self::assertContains(
                $file->getPathname(),
                $allowedFiles,
                sprintf(
                    'Reliable Integration Event publishing must flow through the Outbox relay; [%s] references OutboxEventPublisher directly.',
                    $file->getPathname(),
                ),
            );
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function applicationPhpFiles(string $path): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            yield $candidate;
        }
    }
}
