<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Localization;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AtlasTranslationUsageInventory
{
    /** @var list<string> */
    private const SOURCE_DIRECTORIES = [
        'app',
        'routes/breadcrumbs',
        'resources/views',
    ];

    /** @var list<string> */
    private const PATTERNS = [
        '/\b(?:__|trans)\(\s*[\'\"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
        '/\batlas_breadcrumb_resource_action\(\s*[\'\"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
        '/\bFlashMessage::(?:success|info|warning|error)\(\s*[\'\"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
        '/[\'\"]((?:actions|breadcrumbs|flash|mail|pages|validation)\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
        '/[\'\"](?:labelKey|descriptionKey|bodyPreviewKey|title_key|body_key|description_key|message_key)[\'\"]\s*=>\s*[\'\"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
        '/\b(?:subjectKey|headingKey|actionKey|titleKey|bodyKey|messageKey)\s*:\s*[\'\"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'\"]/',
    ];

    /**
     * @return array<string, list<string>> translation key => repository-relative sources
     */
    public static function inventory(string $repositoryRoot): array
    {
        $inventory = [];

        foreach (self::SOURCE_DIRECTORIES as $relativeDirectory) {
            $directory = $repositoryRoot.DIRECTORY_SEPARATOR.$relativeDirectory;

            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || ! self::isScannable($file->getFilename())) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (! is_string($contents)) {
                    continue;
                }

                $source = ltrim(str_replace($repositoryRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);

                foreach (self::PATTERNS as $pattern) {
                    preg_match_all($pattern, $contents, $matches);

                    foreach ($matches[1] as $key) {
                        $inventory[$key] ??= [];
                        $inventory[$key][] = $source;
                    }
                }
            }
        }

        foreach ($inventory as &$sources) {
            $sources = array_values(array_unique($sources));
            sort($sources);
        }

        ksort($inventory);

        return $inventory;
    }

    /**
     * @param  array<string, list<string>>  $inventory
     * @param  array<string, string>  $catalog
     * @return list<string>
     */
    public static function missingKeys(array $inventory, array $catalog): array
    {
        $missing = [];

        foreach ($inventory as $key => $sources) {
            $value = $catalog[$key] ?? null;

            if (! is_string($value) || trim($value) === '' || $value === $key) {
                $missing[] = sprintf('%s (%s)', $key, implode(', ', $sources));
            }
        }

        sort($missing);

        return $missing;
    }

    private static function isScannable(string $filename): bool
    {
        return str_ends_with($filename, '.php') || str_ends_with($filename, '.blade.php');
    }
}
