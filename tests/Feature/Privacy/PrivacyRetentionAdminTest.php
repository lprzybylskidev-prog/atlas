<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Privacy\Application\Services\DataLifecycleParticipantRegistry;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Search\Application\Contracts\SearchDocumentStore;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchLifecycleProjector;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchDocument;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PrivacyRetentionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_privacy_retention_readiness(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('navigation.breadcrumbs.0.label', 'Admin')
                ->where('navigation.breadcrumbs.1.label', 'Prywatność i retencja')
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.privacy-retention.index'))
                ->where('summary.areas', 8)
                ->where('summary.blockedHardDelete', 1)
                ->where('table.key', 'admin.privacy-retention.coverage')
                ->where('table.state.filters.owner', 'all')
                ->where('table.state.filters.coverage', 'all')
                ->where('subjectTypeOptions.0.value', 'user')
                ->where('subjectTypeOptions.1.value', 'file')
                ->where('subjectTypeOptions.2.value', 'file_object')
                ->where('previewFormDefaults.operation', 'hard_delete')
                ->where('previewFormDefaults.subject_type', 'user')
                ->where('autoSubmitPreview', false)
                ->where('coverage.0.area', 'audit.events'));
    }

    public function test_privacy_retention_coverage_filters_are_backend_applied(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention?owner=files&coverage=implemented')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('summary.visible', 1)
                ->where('table.state.filters.owner', 'files')
                ->where('table.state.filters.coverage', 'implemented')
                ->where('coverage.0.area', 'files.private_objects')
                ->where('coverage.0.ownerModule', 'files'));
    }

    public function test_admin_can_create_high_risk_hard_delete_preview(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'user',
                'subject_identifier' => '01J00000000000000000000ABC',
                'reason' => 'Customer erasure request received through the privacy mailbox.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $previewPublicId = (string) str($response->headers->get('Location'))->after('preview=');

        $this->assertDatabaseHas(DatabaseTable::PRIVACY_OPERATION_REQUESTS, [
            'public_id' => $previewPublicId,
            'operation' => 'hard_delete',
            'subject_type' => 'user',
            'subject_identifier' => '01J00000000000000000000ABC',
            'status' => 'blocked',
            'dry_run' => true,
            'requested_by_user_id' => $admin->id,
            'team_id' => $team->id,
            'confirmation_phrase' => 'HARD DELETE 01J00000000000000000000ABC',
        ]);
        $this->assertDatabaseHas(DatabaseTable::PRIVACY_OPERATION_PREVIEWS, [
            'can_execute' => false,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'privacy',
            'action' => 'privacy.hard_delete_previewed',
            'result' => 'rejected',
            'target_type' => 'user',
            'target_public_id' => '01J00000000000000000000ABC',
            'aggregate_public_id' => $previewPublicId,
            'is_security' => true,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_SECURITY_EVENTS, [
            'category' => 'privacy',
            'action' => 'privacy.hard_delete_previewed',
            'result' => 'rejected',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention?preview='.$previewPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('latestPreview.publicId', $previewPublicId)
                ->where('latestPreview.operation', 'hard_delete')
                ->where('latestPreview.status', 'blocked')
                ->where('latestPreview.confirmationPhrase', 'HARD DELETE 01J00000000000000000000ABC')
                ->where('latestPreview.canExecute', false));
    }

    public function test_preview_payload_exposes_record_details_for_non_zero_impacts(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/anonymization/preview', [
                'subject_type' => 'user',
                'subject_identifier' => $admin->public_id,
                'reason' => 'Customer erasure request requires checking role assignment details.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $previewPublicId = (string) str($response->headers->get('Location'))->after('preview=');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention?preview='.$previewPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('latestPreview.impacts', fn (Collection $impacts): bool => $impacts->contains(
                    static function (array $impact) use ($team): bool {
                        $details = $impact['details'] ?? [];
                        $firstDetail = is_array($details) ? ($details[0] ?? []) : [];

                        return ($impact['dataSet'] ?? null) === 'authorization.user_roles'
                            && ($impact['estimatedRecords'] ?? null) === 1
                            && is_array($firstDetail)
                            && (($firstDetail['team_id'] ?? null) === $team->id);
                    },
                ))
                ->where('latestPreview.impacts', fn (Collection $impacts): bool => $impacts->doesntContain(
                    static fn (array $impact): bool => ($impact['estimatedRecords'] ?? null) === 0,
                )));
    }

    public function test_preview_high_risk_confirmation_returns_to_privacy_screen(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->withHeader('Referer', url('/admin/privacy-retention'))
            ->withSession($this->adminSession($team, highRisk: false))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'user',
                'subject_identifier' => '01J00000000000000000000ABC',
                'reason' => 'Customer erasure request received through the privacy mailbox.',
                'dry_run' => true,
            ])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_HIGH_RISK)
            ->assertSessionHas('url.intended', url('/admin/privacy-retention'));

        $this->actingAs($admin)
            ->withSession([
                ...$this->adminSession($team),
                'atlas_high_risk_recoverable_input' => [
                    'operation' => 'hard_delete',
                    'subject_type' => 'user',
                    'subject_identifier' => '01J00000000000000000000ABC',
                    'reason' => 'Customer erasure request received through the privacy mailbox.',
                    'dry_run' => true,
                ],
            ])
            ->get('/admin/privacy-retention')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('previewFormDefaults.operation', 'hard_delete')
                ->where('previewFormDefaults.subject_type', 'user')
                ->where('previewFormDefaults.subject_identifier', '01J00000000000000000000ABC')
                ->where('previewFormDefaults.reason', 'Customer erasure request received through the privacy mailbox.')
                ->where('previewFormDefaults.dry_run', true)
                ->where('autoSubmitPreview', true));
    }

    public function test_preview_validation_errors_are_shown_before_high_risk_confirmation(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->from('/admin/privacy-retention')
            ->withSession($this->adminSession($team, highRisk: false))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'operation' => 'hard_delete',
                'subject_type' => 'user',
                'subject_identifier' => '01J00000000000000000000ABC',
                'reason' => 'a',
                'dry_run' => true,
            ])
            ->assertRedirect('/admin/privacy-retention')
            ->assertSessionHasErrors('reason')
            ->assertSessionMissing(AdministrativeSessionManager::PENDING_REAUTHENTICATION)
            ->assertSessionHas('_old_input.subject_identifier', '01J00000000000000000000ABC')
            ->assertSessionHas('_old_input.reason', 'a');
    }

    public function test_preview_high_risk_confirmation_falls_back_to_dashboard_for_external_referer(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->withHeader('Referer', 'https://example.test/admin/privacy-retention')
            ->withSession($this->adminSession($team, highRisk: false))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'user',
                'subject_identifier' => '01J00000000000000000000ABC',
                'reason' => 'Customer erasure request received through the privacy mailbox.',
                'dry_run' => true,
            ])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('admin.system-status'));
    }

    public function test_preview_validation_errors_keep_submitted_form_values(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->from('/admin/privacy-retention')
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'operation' => 'hard_delete',
                'subject_type' => 'user',
                'subject_identifier' => '01J00000000000000000000ABC',
                'reason' => 'too short',
                'dry_run' => true,
            ])
            ->assertRedirect('/admin/privacy-retention')
            ->assertSessionHasErrors('reason')
            ->assertSessionHas('_old_input.subject_identifier', '01J00000000000000000000ABC')
            ->assertSessionHas('_old_input.reason', 'too short');
    }

    public function test_file_subject_preview_reports_file_lifecycle_impact(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $filePublicId = '01J00000000000000000000FIL';

        $this->app['db']->table(DatabaseTable::FILE_OBJECTS)->insert([
            'public_id' => $filePublicId,
            'disk' => 'atlas_files',
            'path' => 'privacy/test-file.txt',
            'physical_owner' => true,
            'original_name' => 'privacy-request.txt',
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 128,
            'checksum_sha256' => str_repeat('a', 64),
            'scan_state' => 'clean',
            'scan_state_changed_at' => now(),
            'available_at' => now(),
            'quarantined_at' => now(),
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/anonymization/preview', [
                'subject_type' => 'file',
                'subject_identifier' => $filePublicId,
                'reason' => 'Privacy request requires checking the private file object.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $previewPublicId = (string) str($response->headers->get('Location'))->after('preview=');
        $preview = $this->app['db']->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS.' as requests')
            ->join(DatabaseTable::PRIVACY_OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
            ->where('requests.public_id', $previewPublicId)
            ->firstOrFail(['previews.impacts']);

        self::assertIsString($preview->impacts);
        $impacts = self::jsonList($preview->impacts);

        self::assertTrue(collect($impacts)->contains(
            static fn (array $impact): bool => ($impact['dataSet'] ?? null) === 'files.private_objects'
                && ($impact['estimatedRecords'] ?? null) === 1
                && ($impact['irreversible'] ?? null) === true,
        ));
    }

    public function test_search_indexes_participate_in_privacy_previews(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $subjectIdentifier = '01J00000000000000000000SRH';

        $this->app->bind(SearchIndexRegistry::class, fn (): SearchIndexRegistry => new PrivacyTestSearchIndexRegistry);
        $this->app->bind(SearchDocumentStore::class, fn (): SearchDocumentStore => new PrivacyTestSearchDocumentStore);
        $this->app->bind('tests.privacy.search_lifecycle_projector', fn (): SearchLifecycleProjector => new PrivacyTestSearchLifecycleProjector);
        $this->app->tag(['tests.privacy.search_lifecycle_projector'], 'atlas.search_lifecycle_projectors');
        $this->app->forgetInstance(DataLifecycleParticipantRegistry::class);

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'person',
                'subject_identifier' => $subjectIdentifier,
                'reason' => 'Customer erasure request requires removing derived search projections.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $previewPublicId = (string) str($response->headers->get('Location'))->after('preview=');
        $preview = $this->app['db']->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS.' as requests')
            ->join(DatabaseTable::PRIVACY_OPERATION_PREVIEWS.' as previews', 'previews.operation_request_id', '=', 'requests.id')
            ->where('requests.public_id', $previewPublicId)
            ->firstOrFail(['previews.impacts', 'previews.participant_count']);

        self::assertIsString($preview->impacts);
        $impacts = self::jsonList($preview->impacts);

        self::assertGreaterThanOrEqual(2, $preview->participant_count);
        self::assertTrue(collect($impacts)->contains(
            static fn (array $impact): bool => ($impact['dataSet'] ?? null) === 'search.indexes'
                && ($impact['estimatedRecords'] ?? null) === 1
                && ($impact['irreversible'] ?? null) === true,
        ));
    }

    public function test_admin_can_create_and_view_legal_hold_that_blocks_preview(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $subjectIdentifier = '01J00000000000000000000044';

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/legal-holds', [
                'subject_type' => 'user',
                'subject_identifier' => $subjectIdentifier,
                'reason' => 'Court order requires retaining this subject before erasure.',
                'expires_on' => now('UTC')->addDays(30)->toDateString(),
            ])
            ->assertRedirect('/admin/privacy-retention/legal-holds')
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.legal_hold_created');

        $this->assertDatabaseHas(DatabaseTable::PRIVACY_LEGAL_HOLDS, [
            'subject_type' => 'user',
            'subject_identifier' => $subjectIdentifier,
            'created_by_user_id' => $admin->id,
            'team_id' => $team->id,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'privacy',
            'action' => 'privacy.legal_hold_created',
            'result' => 'succeeded',
            'target_type' => 'user',
            'target_public_id' => $subjectIdentifier,
            'is_security' => true,
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention/legal-holds')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/LegalHolds')
                ->where('navigation.breadcrumbs.1.label', 'Prywatność i retencja')
                ->where('navigation.breadcrumbs.2.label', 'Blokady prawne')
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.privacy-retention.legal-holds.index'))
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.privacy-retention.legal-holds.create'))
                ->where('summary.active', 1)
                ->where('holds.0.subjectIdentifier', $subjectIdentifier)
                ->where('holds.0.status', 'active'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention/legal-holds/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/LegalHoldCreate')
                ->where('navigation.breadcrumbs.1.label', 'Prywatność i retencja')
                ->where('navigation.breadcrumbs.2.label', 'Blokady prawne')
                ->where('navigation.breadcrumbs.3.label', 'Utwórz')
                ->where('subjectTypeOptions.0.value', 'user')
                ->where('subjectTypeOptions.1.value', 'file')
                ->where('subjectTypeOptions.2.value', 'file_object')
                ->where('formDefaults.subject_type', 'user'));

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'user',
                'subject_identifier' => $subjectIdentifier,
                'reason' => 'Customer erasure request received through the privacy mailbox.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $previewPublicId = (string) str($response->headers->get('Location'))->after('preview=');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention?preview='.$previewPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Index')
                ->where('latestPreview.blockers.0.code', 'active_legal_hold'));
    }

    public function test_legal_hold_creation_rejects_unknown_subject_type(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->from('/admin/privacy-retention/legal-holds/create')
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/legal-holds', [
                'subject_type' => 'raw_text_type',
                'subject_identifier' => '01J00000000000000000000044',
                'reason' => 'Court order requires retaining this subject before erasure.',
                'expires_on' => now('UTC')->addDays(30)->toDateString(),
            ])
            ->assertRedirect('/admin/privacy-retention/legal-holds/create')
            ->assertSessionHasErrors('subject_type');
    }

    public function test_admin_can_view_privacy_operation_history_with_filters(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $subjectIdentifier = '01J00000000000000000000055';

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/hard-delete/preview', [
                'subject_type' => 'user',
                'subject_identifier' => $subjectIdentifier,
                'reason' => 'Customer erasure request received through the privacy mailbox.',
                'dry_run' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.preview_blocked');

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/privacy-retention/operations?operation=hard_delete&status=blocked&executable=no')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/PrivacyRetention/Operations')
                ->where('navigation.breadcrumbs.1.label', 'Prywatność i retencja')
                ->where('navigation.breadcrumbs.2.label', 'Operacje')
                ->where('auth.availableAdminRoutes', fn (Collection $routes): bool => $routes->contains('admin.privacy-retention.operations.index'))
                ->where('summary.total', 1)
                ->where('summary.visible', 1)
                ->where('summary.blocked', 1)
                ->where('summary.hardDelete', 1)
                ->where('operations.0.operation', 'hard_delete')
                ->where('operations.0.status', 'blocked')
                ->where('operations.0.subjectIdentifier', $subjectIdentifier)
                ->where('operations.0.canExecute', false)
                ->where('table.key', 'admin.privacy-retention.operations')
                ->where('table.state.filters.operation', 'hard_delete')
                ->where('table.state.filters.status', 'blocked')
                ->where('table.state.filters.executable', 'no'));
    }

    public function test_admin_can_execute_previewed_privacy_operation_through_central_executor(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $subjectIdentifier = '01J00000000000000000000077';
        $operationPublicId = '01J00000000000000000000EXE';

        $this->app->singleton(
            DataLifecycleParticipantRegistry::class,
            fn (): DataLifecycleParticipantRegistry => new DataLifecycleParticipantRegistry([
                new PrivacyTestExecutingLifecycleParticipant,
            ]),
        );

        $operationRequestId = $this->app['db']->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS)->insertGetId([
            'public_id' => $operationPublicId,
            'operation' => 'anonymization',
            'subject_type' => 'person',
            'subject_identifier' => $subjectIdentifier,
            'status' => 'previewed',
            'dry_run' => true,
            'requested_by_user_id' => $admin->id,
            'team_id' => $team->id,
            'reason' => 'Customer verified erasure request after identity review.',
            'confirmation_phrase' => 'ANONYMIZE '.$subjectIdentifier,
            'correlation_id' => 'privacy-execution-request',
            'previewed_at' => now(),
            'metadata' => json_encode(['can_execute' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table(DatabaseTable::PRIVACY_OPERATION_PREVIEWS)->insert([
            'operation_request_id' => $operationRequestId,
            'impacts' => json_encode([[
                'dataSet' => 'tests.subject_records',
                'estimatedRecords' => 2,
                'irreversible' => true,
            ]], JSON_THROW_ON_ERROR),
            'blockers' => json_encode([], JSON_THROW_ON_ERROR),
            'participant_count' => 1,
            'estimated_records' => 2,
            'can_execute' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/privacy-retention/anonymization/'.$operationPublicId.'/execute', [
                'confirmation_phrase' => 'ANONYMIZE '.$subjectIdentifier,
            ])
            ->assertRedirect('/admin/privacy-retention/operations?operation=anonymization&status=executed')
            ->assertSessionHas('flash.messages.0.key', 'flash.privacy.execution_completed');

        $this->assertDatabaseHas(DatabaseTable::PRIVACY_OPERATION_REQUESTS, [
            'public_id' => $operationPublicId,
            'status' => 'executed',
            'dry_run' => false,
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'privacy',
            'action' => 'privacy.anonymization_executed',
            'result' => 'succeeded',
            'target_type' => 'person',
            'target_public_id' => $subjectIdentifier,
            'aggregate_public_id' => $operationPublicId,
            'correlation_id' => 'privacy-execution-request',
            'is_security' => true,
        ]);

        $metadata = $this->app['db']->table(DatabaseTable::PRIVACY_OPERATION_REQUESTS)
            ->where('public_id', $operationPublicId)
            ->value('metadata');

        self::assertIsString($metadata);

        $decoded = self::jsonObject($metadata);

        self::assertSame(2, $decoded['affected_records']);
        self::assertSame(1, $decoded['step_count']);
        $steps = $decoded['steps'] ?? [];
        self::assertIsArray($steps);
        $firstStep = $steps[0] ?? [];
        self::assertIsArray($firstStep);
        self::assertSame('tests.subject_records_anonymized', $firstStep['step']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jsonList(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $rows = [];

        foreach ($decoded as $item) {
            self::assertIsArray($item);
            $row = [];

            foreach ($item as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonObject(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $row = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $row[$key] = $item;
            }
        }

        return $row;
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $admin = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => '01J00000000000000000000043',
            'name' => 'Operations',
            'slug' => 'operations-privacy',
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        $this->app['db']->table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app['db']->table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $admin->id,
            'team_id' => $team->id,
        ]);

        return [$admin, $team];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSession(Team $team, bool $highRisk = true): array
    {
        $session = [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
        ];

        if ($highRisk) {
            $session['atlas_admin_high_risk_confirmed_at'] = now()->toIso8601String();
        }

        return $session;
    }
}

final class PrivacyTestSearchIndexRegistry implements SearchIndexRegistry
{
    public function all(): array
    {
        return [$this->descriptor()];
    }

    public function get(string $indexKey): ?SearchIndexDescriptor
    {
        return $indexKey === 'cases.people' ? $this->descriptor() : null;
    }

    private function descriptor(): SearchIndexDescriptor
    {
        return new SearchIndexDescriptor(
            key: 'cases.people',
            moduleKey: 'cases',
            stableAlias: 'atlas_cases_people',
            searchableFields: ['display_name'],
            filterableFields: ['module_key', 'team_public_ids', 'permission_keys'],
            sortableFields: ['display_name'],
        );
    }
}

final class PrivacyTestSearchDocumentStore implements SearchDocumentStore
{
    public function configure(SearchIndexDescriptor $descriptor): void {}

    public function createPhysicalIndex(SearchIndexDescriptor $descriptor, string $physicalIndex): void {}

    public function upsert(SearchIndexDescriptor $descriptor, SearchDocument $document): void {}

    public function upsertInto(SearchIndexDescriptor $descriptor, string $physicalIndex, SearchDocument $document): void {}

    public function delete(SearchIndexDescriptor $descriptor, array $documentPublicIds): void {}

    public function count(string $indexName): int
    {
        return 0;
    }

    public function promote(SearchIndexDescriptor $descriptor, string $physicalIndex): void {}
}

final class PrivacyTestSearchLifecycleProjector implements SearchLifecycleProjector
{
    public function supports(DataLifecycleSubject $subject, DataLifecycleOperation $operation): bool
    {
        return $subject->type === 'person' && $operation === DataLifecycleOperation::Delete;
    }

    public function documentIdsFor(DataLifecycleSubject $subject, DataLifecycleOperation $operation): array
    {
        return ['cases.people' => [$subject->identifier]];
    }
}

final class PrivacyTestExecutingLifecycleParticipant implements DataLifecycleParticipant
{
    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        return new DataLifecyclePreview([]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        return new DataLifecycleResult([
            new DataLifecycleStepResult(
                step: 'tests.subject_records_anonymized',
                affectedRecords: 2,
                idempotent: true,
            ),
        ]);
    }
}
