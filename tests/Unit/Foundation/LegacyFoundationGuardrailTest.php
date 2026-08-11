<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\Architecture\LegacyReferenceScanner;
use Tests\TestCase;

final class LegacyFoundationGuardrailTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN_REFERENCES = [
        'Admin/Managers',
        '/admin/managers',
        'admin.managers.',
        'navigation.managers',
        'breadcrumbs.managers',
        '/time-tracking/manager-report',
        'time-tracking.reports.manager',
        'ManagerTimeReportController',
        'TimeTrackingManagerReportDataTableExportProvider',
        'RecordActions.vue',
        '<RecordActions',
        'UserTeamAccessWorkflow',
        'TeamMemberAccessWorkflow',
        'usePrivacyRetentionSubnavigation',
    ];

    public function test_removed_foundation_artifacts_have_no_active_references(): void
    {
        $sources = $this->activeSources();

        self::assertGreaterThanOrEqual(500, count($sources), 'The legacy-reference guard must scan the real application non-vacuously.');
        self::assertSame([], (new LegacyReferenceScanner)->violations($sources, self::FORBIDDEN_REFERENCES));
    }

    public function test_removed_paths_and_temporary_inventory_scaffolding_stay_absent(): void
    {
        foreach ([
            'app/Modules/Core/Teams/Presentation/Http/Controllers/ManagerHierarchyAdministrationController.php',
            'app/Modules/Optional/TimeTracking/Presentation/Http/Controllers/ManagerTimeReportController.php',
            'app/Modules/Optional/TimeTracking/Application/Exports/TimeTrackingManagerReportDataTableExportProvider.php',
            'resources/js/Components/RecordActions.vue',
            'resources/js/Components/Teams/TeamMemberAccessWorkflow.vue',
            'resources/js/Components/Users/UserTeamAccessWorkflow.vue',
            'resources/js/Composables/usePrivacyRetentionSubnavigation.ts',
            'resources/js/Pages/Admin/Managers',
            'resources/js/Pages/TimeTracking/ManagerReport.vue',
            'tools/foundation/generate-inventory.php',
            'tools/foundation/refresh-phase28-inventories.php',
            'tools/phase28/generate-inventory.php',
            'docs/roadmap/phase-28-inventories',
        ] as $path) {
            self::assertFileDoesNotExist(base_path($path));
            self::assertDirectoryDoesNotExist(base_path($path));
        }
    }

    public function test_legacy_reference_scanner_rejects_mutation_fixtures(): void
    {
        $violations = (new LegacyReferenceScanner)->violations([
            'routes/web/admin.php' => "Route::get('/admin/managers', ManagerTimeReportController::class);",
            'resources/js/Pages/Unsafe.vue' => "import RecordActions from '../RecordActions.vue';",
        ], self::FORBIDDEN_REFERENCES);

        self::assertSame([
            'resources/js/Pages/Unsafe.vue -> RecordActions.vue',
            'routes/web/admin.php -> /admin/managers',
            'routes/web/admin.php -> ManagerTimeReportController',
        ], $violations);
    }

    /** @return array<string, string> */
    private function activeSources(): array
    {
        $sources = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if (! in_array($file->getExtension(), ['php', 'json', 'ts', 'vue'], true)) {
                    continue;
                }

                $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

                if (str_starts_with($path, 'resources/js/Guardrails/') || str_ends_with($path, '.test.ts')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);
                $sources[$path] = $contents;
            }
        }

        ksort($sources);

        return $sources;
    }
}
