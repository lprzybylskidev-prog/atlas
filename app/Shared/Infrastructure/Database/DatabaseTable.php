<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

final class DatabaseTable
{
    public const USERS = DatabaseSchema::CORE_IDENTITY.'.users';

    public const PASSWORD_RESET_TOKENS = DatabaseSchema::CORE_IDENTITY.'.password_reset_tokens';

    public const USER_PASSWORD_HISTORIES = DatabaseSchema::CORE_IDENTITY.'.user_password_histories';

    public const USER_WEBAUTHN_CREDENTIALS = DatabaseSchema::CORE_IDENTITY.'.user_webauthn_credentials';

    public const RATE_LIMIT_REJECTIONS = DatabaseSchema::CORE_IDENTITY.'.rate_limit_rejections';

    public const SESSIONS = DatabaseSchema::CORE_IDENTITY.'.sessions';

    public const TEAMS = DatabaseSchema::CORE_TEAMS.'.teams';

    public const TEAM_USER_ASSIGNMENTS = DatabaseSchema::CORE_TEAMS.'.team_user_assignments';

    public const TEAM_MANAGER_RELATIONSHIPS = DatabaseSchema::CORE_TEAMS.'.team_manager_relationships';

    public const PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.permissions';

    public const ROLES = DatabaseSchema::CORE_AUTHORIZATION.'.roles';

    public const MODEL_HAS_PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.model_has_permissions';

    public const MODEL_HAS_ROLES = DatabaseSchema::CORE_AUTHORIZATION.'.model_has_roles';

    public const ROLE_HAS_PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.role_has_permissions';

    public const AUTHORIZATION_ONBOARDING_PACKAGES = DatabaseSchema::CORE_AUTHORIZATION.'.authorization_onboarding_packages';

    public const USER_ONBOARDING_PACKAGES = DatabaseSchema::CORE_AUTHORIZATION.'.user_onboarding_packages';

    public const AUDIT_EVENTS = DatabaseSchema::CORE_AUDIT.'.audit_events';

    public const AUDIT_SECURITY_EVENTS = DatabaseSchema::CORE_AUDIT.'.audit_security_events';

    public const SETTINGS_GLOBAL_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_global_values';

    public const SETTINGS_TEAM_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_team_values';

    public const SETTINGS_USER_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_user_values';

    public const SETTINGS_SECURITY_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_security_values';

    public const NOTIFICATIONS = DatabaseSchema::CORE_NOTIFICATIONS.'.notifications';

    public const NOTIFICATION_RECIPIENTS = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_recipients';

    public const NOTIFICATION_EMAIL_ADDRESSES = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_email_addresses';

    public const NOTIFICATION_EMAIL_PREFERENCES = DatabaseSchema::CORE_NOTIFICATIONS.'.notification_email_preferences';

    public const REALTIME_EVENTS = DatabaseSchema::CORE_NOTIFICATIONS.'.realtime_events';

    public const FILE_OBJECTS = DatabaseSchema::CORE_FILES.'.file_objects';

    public const FILE_SCAN_EVIDENCE = DatabaseSchema::CORE_FILES.'.file_scan_evidence';

    public const PRIVACY_OPERATION_REQUESTS = DatabaseSchema::CORE_PRIVACY.'.operation_requests';

    public const PRIVACY_OPERATION_PREVIEWS = DatabaseSchema::CORE_PRIVACY.'.operation_previews';

    public const PRIVACY_LEGAL_HOLDS = DatabaseSchema::CORE_PRIVACY.'.legal_holds';

    public const INTEGRATION_CONNECTIONS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.integration_connections';

    public const INTEGRATION_CREDENTIALS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.integration_credentials';

    public const INTEGRATION_EXTERNAL_ID_MAPPINGS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.external_id_mappings';

    public const INTEGRATION_SYNC_RUNS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.synchronization_runs';

    public const INTEGRATION_IDEMPOTENCY_KEYS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.idempotency_keys';

    public const INTEGRATION_CIRCUIT_BREAKERS = DatabaseSchema::OPTIONAL_INTEGRATIONS.'.circuit_breakers';

    public const MANAGED_PROCESS_DEFINITIONS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_definitions';

    public const MANAGED_PROCESS_RUNS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_runs';

    public const MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_run_acknowledgements';

    public const MANAGED_PROCESS_LOG_EVENTS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_log_events';

    public const MANAGED_PROCESS_SCHEDULES = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_schedules';

    public const IMPORT_EXECUTIONS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_executions';

    public const IMPORT_ROW_ERRORS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_row_errors';

    public const IMPORT_IDEMPOTENCY_KEYS = DatabaseSchema::OPTIONAL_IMPORTS.'.import_idempotency_keys';

    public const FEATURE_FLAG_GLOBAL_VALUES = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_global_values';

    public const FEATURE_FLAG_TEAM_VALUES = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_team_values';

    public const FEATURE_FLAG_HISTORY = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_history';

    public const TIME_TRACKING_USER_TEAM_SETTINGS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.user_team_settings';

    public const TIME_TRACKING_WORK_SESSIONS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.work_sessions';

    public const TIME_TRACKING_MODULE_CONTEXT_SEGMENTS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.module_context_segments';

    public const TIME_TRACKING_BREAKS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.breaks';

    public const TIME_TRACKING_BREAK_REMINDERS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.break_reminders';

    public const TIME_TRACKING_OTHER_WORK_CATEGORIES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.other_work_categories';

    public const TIME_TRACKING_OTHER_WORK = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.other_work';

    public const TIME_TRACKING_MAINTENANCE_WINDOWS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.maintenance_windows';

    public const TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.maintenance_affected_sessions';

    public const TIME_TRACKING_CORRECTION_REQUESTS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_requests';

    public const TIME_TRACKING_CORRECTION_PROPOSALS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_proposals';

    public const TIME_TRACKING_CORRECTION_HISTORY = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_history';

    public const TIME_TRACKING_CLOSED_PERIOD_OVERRIDES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.closed_period_overrides';

    public const TIME_TRACKING_SETTLEMENT_SETTINGS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.settlement_settings';

    public const TIME_TRACKING_SETTLEMENT_PERIODS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.settlement_periods';

    public const TIME_TRACKING_BREAK_POLICIES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.break_policies';

    public const REPORT_EXPORT_REQUESTS = DatabaseSchema::CORE_EXPORTS.'.export_requests';

    public const REPORT_EXPORT_ARTIFACTS = DatabaseSchema::CORE_EXPORTS.'.export_artifacts';

    public const REPORT_RENDER_CREDENTIALS = DatabaseSchema::CORE_EXPORTS.'.render_credentials';

    public const CACHE = DatabaseSchema::SHARED.'.cache';

    public const CACHE_LOCKS = DatabaseSchema::SHARED.'.cache_locks';

    public const JOBS = DatabaseSchema::SHARED.'.jobs';

    public const JOB_BATCHES = DatabaseSchema::SHARED.'.job_batches';

    public const FAILED_JOBS = DatabaseSchema::SHARED.'.failed_jobs';

    public const FAILED_JOB_ACKNOWLEDGEMENTS = DatabaseSchema::SHARED.'.failed_job_acknowledgements';

    public const OUTBOX_EVENTS = DatabaseSchema::SHARED.'.outbox_events';

    public const OUTBOX_CONSUMED_EVENTS = DatabaseSchema::SHARED.'.outbox_consumed_events';

    public const TABLE_SAVED_VIEWS = DatabaseSchema::SHARED.'.table_saved_views';

    public const TABLE_SAVED_VIEW_DEFAULTS = DatabaseSchema::SHARED.'.table_saved_view_defaults';

    public const MODULE_GLOBAL_STATES = DatabaseSchema::SHARED.'.module_global_states';

    public const MODULE_TEAM_STATES = DatabaseSchema::SHARED.'.module_team_states';

    public const MODULE_ACTIVATION_SCHEDULES = DatabaseSchema::SHARED.'.module_activation_schedules';

    public const MODULE_ACTIVATION_HISTORY = DatabaseSchema::SHARED.'.module_activation_history';

    public const SCHEDULER_HEARTBEATS = DatabaseSchema::SHARED.'.scheduler_heartbeats';

    public static function unqualified(string $table): string
    {
        $parts = explode('.', $table);

        return end($parts) ?: $table;
    }
}
