<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;

final class Phase28InventoryGeneratorTest extends TestCase
{
    public function test_phase_28_inventory_generator_outputs_non_vacuous_json(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --json';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $inventory = json_decode(implode("\n", $output), true);

        self::assertIsArray($inventory);
        $totals = $inventory['totals'] ?? null;
        $orphanModuleRoots = $inventory['orphan_module_roots'] ?? null;

        self::assertIsArray($totals);
        self::assertIsArray($orphanModuleRoots);
        self::assertGreaterThanOrEqual(18, $totals['modules'] ?? 0);
        self::assertGreaterThanOrEqual(60, $totals['vue_pages'] ?? 0);
        self::assertGreaterThanOrEqual(30, $totals['migrations'] ?? 0);
        self::assertGreaterThanOrEqual(10, $totals['public_persistence_class_count'] ?? 0);
        self::assertGreaterThanOrEqual(100, $totals['public_persistence_use_count'] ?? 0);
        self::assertGreaterThan(0, $totals['mail_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['seeder_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['runtime_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['runtime_matrix_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['backend_surface_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['audit_matrix_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['frontend_consistency_signal_count'] ?? 0);
        self::assertGreaterThan(0, $totals['global_module_import_count'] ?? 0);
        self::assertGreaterThan(0, $totals['migration_schema_operation_count'] ?? 0);
        self::assertGreaterThan(0, $totals['migration_after_usage_count'] ?? 0);
        self::assertContains('app/Modules/Application/Demo', $orphanModuleRoots);
    }

    public function test_phase_28_inventory_generator_reports_key_module_drift(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --json';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $inventory = json_decode(implode("\n", $output), true);

        self::assertIsArray($inventory);
        self::assertIsArray($inventory['modules'] ?? null);

        $modules = [];
        foreach ($inventory['modules'] as $module) {
            self::assertIsArray($module);
            $moduleKey = $module['key'] ?? null;

            self::assertIsString($moduleKey);

            $modules[$moduleKey] = $module;
        }

        self::assertArrayHasKey('exports', $modules);
        self::assertArrayHasKey('privacy', $modules);
        $exportsRequiredDependencies = $modules['exports']['required_dependencies'] ?? null;
        $privacyRequiredDependencies = $modules['privacy']['required_dependencies'] ?? null;
        $exportsActualImports = $modules['exports']['actual_module_imports'] ?? null;
        $privacyActualImports = $modules['privacy']['actual_module_imports'] ?? null;

        self::assertIsArray($exportsRequiredDependencies);
        self::assertIsArray($privacyRequiredDependencies);
        self::assertIsArray($exportsActualImports);
        self::assertIsArray($privacyActualImports);
        self::assertContains('managed_processes', $exportsRequiredDependencies);
        self::assertContains('managed_processes', $privacyRequiredDependencies);
        self::assertContains('Optional\\ManagedProcesses', $exportsActualImports);
        self::assertContains('Optional\\ManagedProcesses', $privacyActualImports);
    }

    public function test_phase_28_inventory_generator_outputs_framework_route_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --framework-routes-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('| `administrator` |', $markdown);
        self::assertStringContainsString('| `manager` |', $markdown);
        self::assertStringContainsString('| `regular_user` |', $markdown);
        self::assertStringContainsString('admin.system-status', $markdown);
        self::assertStringContainsString('`Admin/SystemStatus`', $markdown);
        self::assertStringContainsString('| `regular_user` | `GET\\|HEAD` | `login` | `login` | `Closure` | `Auth/Login` |', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_persistence_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --persistence-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 persistence matrix', $markdown);
        self::assertStringContainsString('`core_identity.users`', $markdown);
        self::assertStringContainsString('`optional_time_tracking.work_sessions`', $markdown);
        self::assertStringContainsString('| `identity` |', $markdown);
        self::assertStringContainsString('| `optional_time_tracking.work_sessions` | `user_id` | `core_identity.users` |', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_runtime_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --runtime-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 runtime/config matrix', $markdown);
        self::assertStringContainsString('| `queue_worker` |', $markdown);
        self::assertStringContainsString('| `clamav` |', $markdown);
        self::assertStringContainsString('| `chromium_pdf` |', $markdown);
        self::assertStringContainsString('P28-RUNTIME-005', $markdown);
        self::assertStringContainsString('pnpm@latest', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_backend_surface_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --backend-surfaces-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 backend surface matrix', $markdown);
        self::assertStringContainsString('| `administrator` |', $markdown);
        self::assertStringContainsString('| `worker_scheduler` |', $markdown);
        self::assertStringContainsString('| `mail` |', $markdown);
        self::assertStringContainsString('`http_route`', $markdown);
        self::assertStringContainsString('`command_class`', $markdown);
        self::assertStringContainsString('`notification_class`', $markdown);
        self::assertStringContainsString('admin.system-status', $markdown);
        self::assertStringContainsString('P28-INV-002', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_audit_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --audit-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 audit event matrix', $markdown);
        self::assertStringContainsString('| `identity` |', $markdown);
        self::assertStringContainsString('| `time_tracking` |', $markdown);
        self::assertStringContainsString('`AuditRecorder`', $markdown);
        self::assertStringContainsString('`SecurityAuditRecorder`', $markdown);
        self::assertStringContainsString('`auth.session_conflict`', $markdown);
        self::assertStringContainsString('P28-AUDIT-001', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_frontend_consistency_matrix(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --frontend-consistency-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 frontend consistency matrix', $markdown);
        self::assertStringContainsString('| `administrator` |', $markdown);
        self::assertStringContainsString('| `navigation` |', $markdown);
        self::assertStringContainsString('`large_page`', $markdown);
        self::assertStringContainsString('`app_layout`', $markdown);
        self::assertStringContainsString('resources/js/Pages/TimeTracking/AdminOperations.vue', $markdown);
        self::assertStringContainsString('P28-INV-003', $markdown);
    }

    public function test_phase_28_inventory_generator_outputs_prep_report(): void
    {
        $basePath = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($basePath).' && php tools/phase28/generate-inventory.php --prep-report-markdown';

        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));

        $markdown = implode("\n", $output);

        self::assertStringContainsString('# Phase 28 prep report', $markdown);
        self::assertStringContainsString('| Phase 28 prep | `100%` |', $markdown);
        self::assertStringContainsString('| Frontend product consistency review | `recorded in phase file` |', $markdown);
        self::assertStringContainsString('| Phase 28 implementation | `0%` |', $markdown);
        self::assertStringContainsString('P28-W02A', $markdown);
        self::assertStringContainsString('P28-W05A', $markdown);
        self::assertStringContainsString('Module dependency drift', $markdown);
        self::assertStringContainsString('Public persistence leakage', $markdown);
        self::assertStringContainsString('Runtime drift', $markdown);
        self::assertStringContainsString('Audit normalization', $markdown);
        self::assertStringContainsString('Frontend product consistency', $markdown);
    }
}
