<?php

declare(strict_types=1);

$allSources = [
    'admin', 'admin-ui', 'application', 'cli', 'e2e', 'global', 'http', 'manual',
    'queue', 'scheduled', 'scheduler', 'settings', 'system', 'test', 'ui', 'web',
];

$securityCategories = [
    'administrative_mode', 'authentication', 'authorization', 'files', 'identity',
    'impersonation', 'integrations', 'mfa', 'password', 'privacy', 'queue_operations',
    'rate_limit', 'security', 'session', 'settings',
];

$commonMetadata = [
    'action', 'actor_scope', 'affected_records', 'allowed', 'attempt', 'blocker_codes',
    'can_execute', 'category_key', 'changed_fields', 'correlation_id', 'count',
    'created_by', 'decision', 'deleted_temporary_files', 'description_present',
    'dry_run', 'effective', 'effective_at', 'enabled', 'end_note_present', 'estimated_records', 'failed_temporary_deletes',
    'failure_code', 'field', 'file_count', 'filename', 'files', 'from', 'guard',
    'idempotency_key', 'impersonation_session_id', 'integration_key', 'ip_address',
    'module', 'module_key', 'new_run_id', 'operation', 'package', 'participant_count',
    'permission', 'permissions', 'policy', 'process_key', 'reason', 'record_count',
    'requested_file_public_id', 'result', 'role', 'roles', 'route', 'schedule_public_id', 'scope', 'source',
    'source_id', 'source_type', 'status', 'step_count', 'table_key', 'team_id', 'team_public_id',
    'ticket', 'ttl_minutes', 'type', 'user_agent', 'user_public_id', 'view_public_id',
    'view_type',
];

/**
 * @param  list<string>  $actions
 * @param  list<string>  $targetTypes
 * @param  list<string>  $aggregateTypes
 * @param  list<string>  $metadataKeys
 * @param  list<string>  $sources
 * @param  list<string>  $categories
 * @return array{actions: list<string>, sources: list<string>, target_types: list<string>, aggregate_types: list<string>, metadata_keys: list<string>, security_categories: list<string>}
 */
$catalog = static function (
    array $actions,
    array $targetTypes = [],
    array $aggregateTypes = [],
    array $metadataKeys = [],
    array $sources = [],
    array $categories = [],
) use ($allSources, $commonMetadata, $securityCategories): array {
    sort($actions);

    return [
        'actions' => $actions,
        'sources' => $sources === [] ? $allSources : $sources,
        'target_types' => $targetTypes,
        'aggregate_types' => $aggregateTypes,
        'metadata_keys' => [...$commonMetadata, ...$metadataKeys],
        'security_categories' => $categories === [] ? $securityCategories : $categories,
    ];
};

return [
    'modules' => [
        'identity' => $catalog([
            'admin_mode.enter', 'admin_mode.exit', 'admin_mode.high_risk_confirm',
            'auth.login', 'auth.login_failure', 'auth.login_lock', 'auth.login_success', 'auth.logout',
            'auth.session_conflict', 'auth.session_conflict_resolved', 'e2e.audit.alpha', 'impersonation.end',
            'impersonation.sensitive_override', 'impersonation.start', 'rate_limit.counter_reset',
            'session.active_team_switched', 'user.first_password_set', 'user.login_unlock',
            'user.login_lock', 'user.mfa_reset', 'user.password_changed', 'user.password_reset', 'user.sessions_invalidated',
        ], ['rate_limit_counter', 'user', 'session', 'rate_limit_policy', 'team'], ['user', 'impersonation_session'], [
            'active_session_count', 'failed_attempts', 'high_risk_operation', 'limiter_key', 'limiter_key_hash',
            'lock_count', 'locked', 'locked_until', 'target_online', 'terminated_session_count',
        ]),
        'authorization' => $catalog([
            'authorization.administrator_role_update', 'authorization.first_administrator_bootstrap',
            'authorization.role_created', 'authorization.role_delete_rejected', 'authorization.role_deleted',
            'authorization.role_updated', 'authorization.user_onboarding_package_applied',
            'authorization.user_team_assignments_replaced', 'module.deactivation', 'module.deactivation_attempted',
            'module.schedule_failed', 'queue.failed_job_acknowledge', 'queue.failed_job_retry',
            'queue.failed_jobs_acknowledge', 'queue.failed_jobs_retry',
        ], ['role', 'user', 'module', 'module_activation_schedule', 'failed_job', 'failed_jobs'], ['role', 'user', 'module', 'queue'], [
            'first_password_link_issued', 'missing_count', 'queues', 'schedule_id', 'uuids',
        ]),
        'modules' => $catalog([
            'module.activation_rejected', 'module.activation_scheduled', 'module.global_activation_changed',
            'module.schedule_cancelled', 'module.schedule_rejected', 'module.team_activation_changed',
            'module.team_override_cleared',
        ], ['module'], ['module'], ['error']),
        'shared' => $catalog([
            'e2e.audit.beta', 'table_saved_view.created', 'table_saved_view.default_set', 'table_saved_view.deleted', 'table_saved_view.updated',
        ], ['table_view'], ['table_saved_view']),
        'teams' => $catalog([
            'team.activated', 'team.created', 'team.deactivated', 'team.delete_rejected', 'team.deleted',
            'team.head_manager.updated', 'team.manager_relationship.created', 'team.manager_relationship.ended',
            'team.updated', 'team.user_access_added', 'team.user_access_remove_rejected', 'team.user_access_removed',
        ], ['team', 'user', 'manager_relationship', 'team_user_assignment'], ['team', 'manager_relationship']),
        'settings' => $catalog(['settings.security.updated'], ['security_setting', 'setting'], ['settings']),
        'files' => $catalog([
            'file.anonymized', 'file.deduplicated', 'file.delete_requested', 'file.deleted',
            'file.download_blocked', 'file.download_missing', 'file.downloaded', 'file.generated',
            'file.replaced', 'file.rescan_requested', 'file.retention_copy_created',
            'file.retention_export_created', 'file.scan_acknowledge', 'file.scan_completed',
            'file.scan_failed', 'file.scan_started', 'file.temporary_pruned', 'file.uploaded',
        ], ['file'], ['file'], [
            'canonical_file_object_id', 'canonical_file_public_id', 'checksum_sha256', 'deduplicated', 'disk', 'mime_type',
            'original_name', 'path', 'physical_deleted', 'physical_owner', 'provider', 'purpose',
            'replacement_file_public_id', 'requested_from', 'retention_copy_file_public_id', 'retention_export_file_public_id',
            'scan_state', 'size_bytes', 'threat_name',
        ]),
        'privacy' => $catalog([
            'privacy.anonymization_executed', 'privacy.anonymization_previewed',
            'privacy.hard_delete_executed', 'privacy.hard_delete_previewed', 'privacy.legal_hold_created', 'privacy.legal_hold_released',
        ], ['file', 'person', 'user', 'team', 'subject'], ['privacy_operation_request', 'privacy_legal_hold'], [
            'expires_on', 'rejection', 'subject_identifier', 'subject_type',
        ]),
        'integrations' => $catalog([
            'integration.connection_tested', 'integration.external_api_blocked',
            'integration.external_id_mapped', 'integration.idempotency_replayed',
            'integration.operation_failed', 'integration.operation_succeeded',
        ], ['integration'], ['integration'], ['client_key', 'entity_type', 'source_system']),
        'managed_processes' => $catalog([
            'managed_process.run_acknowledge', 'managed_process.run_cancelled',
            'managed_process.run_created', 'managed_process.run_retried', 'managed_process.runs_acknowledge',
        ], ['managed_process_run', 'managed_process_runs'], ['managed_process'], [
            'module_keys', 'process_keys', 'run_public_ids',
        ]),
        'feature_flags' => $catalog([
            'feature_flag.global_updated', 'feature_flag.team_cleared', 'feature_flag.team_updated',
        ], ['feature_flag'], ['feature_flag']),
        'time_tracking' => $catalog([
            'time_tracking.admin_break_excess_converted', 'time_tracking.admin_break_force_closed',
            'time_tracking.admin_correction_correct', 'time_tracking.admin_correction_reject',
            'time_tracking.admin_manual_entry_created', 'time_tracking.admin_other_work_category_deactivated',
            'time_tracking.admin_other_work_category_saved', 'time_tracking.admin_other_work_force_closed',
            'time_tracking.admin_work_session_terminated', 'time_tracking.break_ended',
            'time_tracking.break_force_closed', 'time_tracking.break_reminders_recorded',
            'time_tracking.break_started', 'time_tracking.breaks_expired_closed',
            'time_tracking.closed_period_correction_created', 'time_tracking.closed_period_override_created',
            'time_tracking.closed_period_override_rejected', 'time_tracking.correction_cancelled',
            'time_tracking.correction_corrected', 'time_tracking.correction_rejected',
            'time_tracking.correction_requested', 'time_tracking.inactivity_logout',
            'time_tracking.maintenance_completed', 'time_tracking.maintenance_emergency_started',
            'time_tracking.maintenance_return_recorded', 'time_tracking.maintenance_scheduled',
            'time_tracking.maintenance_scheduled_started', 'time_tracking.manual_entry_created',
            'time_tracking.other_work_approved', 'time_tracking.other_work_ended',
            'time_tracking.other_work_force_closed', 'time_tracking.other_work_moved_under_review',
            'time_tracking.other_work_rejected', 'time_tracking.other_work_started',
            'time_tracking.review_open_items', 'time_tracking.source_final_correction_created',
            'time_tracking.work_session_closed',
        ], ['user'], [
            'time_tracking_break', 'time_tracking_correction_request', 'time_tracking_maintenance',
            'time_tracking_maintenance_window',
            'time_tracking_other_work', 'time_tracking_other_work_category', 'time_tracking_work_session',
        ], [
            'approval_status', 'authorized_at', 'closed_count', 'closure_reason', 'converted_seconds',
            'correction_request_id', 'decided_at', 'ended_at', 'exact_seconds', 'final_exact_seconds',
            'maximum_single_break_minutes', 'minutes', 'no_eligible_head_manager', 'occurred_at',
            'rejection_reason', 'reminder_count',
            'request_type', 'requested_at', 'requires_manager_review', 'started_at',
        ]),
        'tests' => $catalog([
            'audit.append_only_probe', 'audit.atomicity_probe', 'audit.impersonated_action_probe',
            'audit.no_request_context_probe', 'audit.redaction_probe',
        ], metadataKeys: ['headers', 'safe_reference']),
    ],
    'required_operation_coverage' => [
        [
            'module' => 'authorization',
            'actions' => ['module.deactivation_attempted', 'module.deactivation'],
            'outcomes' => ['succeeded', 'rejected'],
            'test' => 'tests/Feature/Foundation/ModuleActivationAdministrationTest.php',
        ],
        [
            'module' => 'integrations',
            'actions' => ['integration.connection_tested'],
            'outcomes' => ['succeeded', 'rejected', 'failed'],
            'test' => 'tests/Feature/Integrations/IntegrationsAdminTest.php',
        ],
        [
            'module' => 'files',
            'actions' => [
                'file.scan_failed', 'file.rescan_requested', 'file.delete_requested',
                'file.retention_copy_created', 'file.retention_export_created',
            ],
            'outcomes' => ['succeeded', 'rejected', 'failed'],
            'test' => 'tests/Integration/Files/FilesModuleTest.php',
        ],
        [
            'module' => 'privacy',
            'actions' => ['privacy.hard_delete_executed', 'privacy.anonymization_executed'],
            'outcomes' => ['succeeded', 'rejected', 'failed'],
            'test' => 'tests/Feature/Privacy/PrivacyRetentionAdminTest.php',
        ],
        [
            'module' => 'time_tracking',
            'actions' => ['time_tracking.other_work_approved', 'time_tracking.other_work_rejected'],
            'outcomes' => ['succeeded', 'rejected'],
            'test' => 'tests/Feature/TimeTracking/AdminTimeTrackingOperationsRouteTest.php',
        ],
        [
            'module' => 'tests',
            'actions' => ['audit.atomicity_probe'],
            'outcomes' => ['succeeded'],
            'test' => 'tests/Feature/Foundation/AuditFoundationTest.php',
        ],
    ],
];
