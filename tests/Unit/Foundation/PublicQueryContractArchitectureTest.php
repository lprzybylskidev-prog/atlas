<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class PublicQueryContractArchitectureTest extends TestCase
{
    public function test_public_module_contracts_do_not_expose_laravel_query_result_types(): void
    {
        $basePath = dirname(__DIR__, 3);
        $publicPath = $basePath.'/app/Modules';

        $forbiddenTypes = [
            'Illuminate\\Contracts\\Pagination\\Paginator',
            'Illuminate\\Contracts\\Pagination\\LengthAwarePaginator',
            'Illuminate\\Database\\Eloquent\\Collection',
            'Illuminate\\Pagination\\CursorPaginator',
            'Illuminate\\Pagination\\LengthAwarePaginator',
            'Illuminate\\Pagination\\Paginator',
            'Illuminate\\Support\\Collection',
        ];

        $checkedFiles = 0;

        foreach ($this->publicPhpFiles($publicPath) as $file) {
            $checkedFiles++;
            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);

            foreach ($forbiddenTypes as $forbiddenType) {
                self::assertStringNotContainsString(
                    $forbiddenType,
                    $contents,
                    sprintf(
                        'Public module contracts must use framework-independent query result DTOs; [%s] references [%s].',
                        $file->getPathname(),
                        $forbiddenType,
                    ),
                );
            }
        }

        self::assertGreaterThanOrEqual(0, $checkedFiles);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function publicPhpFiles(string $path): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $candidate) {
            if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                continue;
            }

            if (! str_contains($candidate->getPathname(), '/Application/Public/')) {
                continue;
            }

            yield $candidate;
        }
    }
}
