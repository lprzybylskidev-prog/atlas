<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class MailArchitectureTest extends TestCase
{
    public function test_atlas_mail_paths_cannot_bypass_the_bilingual_mail_foundation(): void
    {
        $violations = [];

        foreach ($this->phpFiles(base_path('app')) as $file) {
            $relative = str_replace(base_path().'/', '', $file);
            $contents = file_get_contents($file);

            if (! is_string($contents)) {
                continue;
            }

            if (preg_match('/Mail::(?:raw|html)\s*\(/', $contents) === 1) {
                $violations[] = $relative.' uses a raw mail callback.';
            }

            if ((str_contains($contents, 'MailMessage') || str_contains($contents, 'extends Mailable'))
                && preg_match('/->(?:subject|line|action|greeting|salutation)\s*\(\s*[\'\"]/', $contents) === 1) {
                $violations[] = $relative.' contains hardcoded mail copy.';
            }

            if (str_contains($contents, 'extends Mailable')
                && ! in_array($relative, [
                    'app/Shared/Infrastructure/Mail/AtlasBilingualMail.php',
                    'app/Shared/Infrastructure/Operations/OperationalAlertMail.php',
                ], true)) {
                $violations[] = $relative.' defines a parallel mailable.';
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
        self::assertSame(
            [resource_path('views/mail/atlas-bilingual.blade.php')],
            glob(resource_path('views/mail/*.blade.php')) ?: [],
        );
    }

    public function test_mail_translation_keys_have_polish_and_english_values(): void
    {
        $polish = $this->translationCatalog(base_path('lang/pl.json'));
        $english = $this->translationCatalog(base_path('lang/en.json'));
        $mailKeys = array_values(array_filter(array_keys($polish), static fn (string $key): bool => str_starts_with($key, 'mail.')));

        self::assertNotEmpty($mailKeys);
        self::assertSame($mailKeys, array_values(array_filter(
            array_keys($english),
            static fn (string $key): bool => str_starts_with($key, 'mail.'),
        )));

        foreach ($mailKeys as $key) {
            self::assertNotSame('', trim($polish[$key]));
            self::assertNotSame('', trim($english[$key]));
        }
    }

    /** @return array<string, string> */
    private function translationCatalog(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            self::fail(sprintf('Translation catalog [%s] must contain a JSON object.', $path));
        }

        $catalog = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                self::fail(sprintf('Translation catalog [%s] must contain only string keys and values.', $path));
            }

            $catalog[$key] = $value;
        }

        return $catalog;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
