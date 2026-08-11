<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class IncompleteCapabilityGuardrailTest extends TestCase
{
    public function test_removed_identity_and_time_tracking_surfaces_cannot_reappear_silently(): void
    {
        self::assertFileDoesNotExist(base_path('app/Modules/Core/Identity/Application/WebAuthn/WebAuthnOptionsFactory.php'));
        self::assertFileDoesNotExist(base_path('app/Modules/Core/Identity/Application/WebAuthn/Contracts/WebAuthnCredentialRepository.php'));
        self::assertFileDoesNotExist(base_path('resources/js/Pages/TimeTracking/ManagerReport.vue'));
        self::assertFileDoesNotExist(base_path('app/Modules/Optional/TimeTracking/Presentation/Http/Controllers/ManagerTimeReportController.php'));
        self::assertFileDoesNotExist(base_path('app/Modules/Optional/TimeTracking/Application/Exports/TimeTrackingManagerReportDataTableExportProvider.php'));
        self::assertStringNotContainsString('web-auth/webauthn-lib', $this->contents('composer.json'));
        self::assertStringNotContainsString('user_webauthn_credentials', $this->contents('database/migrations/0001_01_01_000000_create_users_table.php'));
        $securityConfiguration = config('atlas.security', []);
        self::assertIsArray($securityConfiguration);
        self::assertArrayNotHasKey('webauthn', $securityConfiguration);
        self::assertFalse(Route::has('time-tracking.reports.manager'));
    }

    public function test_canonical_docs_do_not_restore_closed_w10_deferrals(): void
    {
        $documents = implode("\n", [
            $this->contents('docs/modules/identity-authentication-and-sessions.md'),
            $this->contents('docs/modules/privacy.md'),
            $this->contents('docs/modules/audit.md'),
            $this->contents('docs/modules/exports.md'),
            $this->contents('docs/modules/time-tracking.md'),
        ]);

        foreach ([
            'WebAuthn/passkey and FIDO2 hardware-key backend support',
            'The current screens do not expose an execute button yet',
            'Phase 28 must verify and complete the separately authorized detailed Audit/history export',
            'manager report currently retains a temporary composition',
            'Any incomplete notification transport',
        ] as $closedDeferral) {
            self::assertStringNotContainsString($closedDeferral, $documents);
        }

    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        self::assertIsString($contents);

        return $contents;
    }
}
