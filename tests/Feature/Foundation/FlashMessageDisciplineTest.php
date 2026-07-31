<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class FlashMessageDisciplineTest extends TestCase
{
    #[Test]
    public function atlas_http_responses_queue_at_most_one_flash_message_per_action(): void
    {
        foreach ($this->phpFiles(app_path()) as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            foreach ($this->flashMessageArrays($contents) as $flashMessages) {
                if ($this->isSingleConditionalFlashMessage($flashMessages)) {
                    continue;
                }

                self::assertLessThanOrEqual(
                    1,
                    substr_count($flashMessages, 'FlashMessage::'),
                    sprintf('Flash message response in [%s] should contain at most one user-facing message.', $file->getPathname()),
                );
            }
        }
    }

    #[Test]
    public function atlas_flash_messages_use_translation_keys_instead_of_inline_copy(): void
    {
        foreach ($this->phpFiles(app_path()) as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all("/FlashMessage::(?:success|info|warning|error)\\(\\s*'([^']+)'/", $contents, $matches);

            foreach ($matches[1] as $key) {
                self::assertStringStartsWith(
                    'flash.',
                    $key,
                    sprintf('Flash message [%s] in [%s] should use a stable flash.* translation key.', $key, $file->getPathname()),
                );
            }
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $path): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function flashMessageArrays(string $contents): array
    {
        preg_match_all("/with\\('flash\\.messages',\\s*\\[(.*?)\\]\\s*\\)/s", $contents, $matches);

        return $matches[1];
    }

    private function isSingleConditionalFlashMessage(string $flashMessages): bool
    {
        return substr_count($flashMessages, 'FlashMessage::') === 2
            && str_contains($flashMessages, '?')
            && str_contains($flashMessages, ':');
    }
}
