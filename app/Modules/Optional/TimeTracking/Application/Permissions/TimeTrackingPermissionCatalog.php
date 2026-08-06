<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class TimeTrackingPermissionCatalog implements ModulePermissionContribution
{
    public const MANAGER_PANEL = 'time-tracking.panels.manager';

    public const USER_REPORT = 'users.work-time';

    public const USER_CORRECTION_REQUEST_STORE = 'users.work-time.corrections.store';

    public const MANAGER_REPORT = 'time-tracking.reports.manager';

    public const MANAGER_WORK_TIME_SUMMARY = 'manager.work-time.summary.index';

    public const MANAGER_WORK_TIME_OTHER_WORK = 'manager.work-time.other-work.index';

    public const MANAGER_WORK_TIME_WORK_SESSIONS = 'manager.work-time.work-sessions.index';

    public const MANAGER_WORK_TIME_BREAKS = 'manager.work-time.breaks.index';

    public const MANAGER_WORK_TIME_CORRECTIONS = 'manager.work-time.corrections.index';

    public const MANAGER_WORK_TIME_WORK_SESSION_SHOW = 'manager.work-time.work-sessions.show';

    public const MANAGER_WORK_TIME_BREAK_SHOW = 'manager.work-time.breaks.show';

    public const MANAGER_WORK_TIME_OTHER_WORK_SHOW = 'manager.work-time.other-work.show';

    public const MANAGER_WORK_TIME_CORRECTION_SHOW = 'manager.work-time.corrections.show';

    public const MANAGER_TERMINATE_SESSION = 'manager.work-time.work-sessions.terminate';

    public const MANAGER_BREAK_FORCE_CLOSE = 'manager.work-time.breaks.force-close';

    public const MANAGER_BREAK_CONVERT_EXCESS = 'manager.work-time.breaks.convert-excess';

    public const MANAGER_OTHER_WORK_FORCE_CLOSE = 'manager.work-time.other-work.force-close';

    public const MANAGER_OTHER_WORK_DECIDE = 'manager.work-time.other-work.decide';

    public const MANAGER_CORRECTION_DECIDE = 'manager.work-time.corrections.decide';

    public const MANAGER_OTHER_WORK_CATEGORY_INDEX = 'manager.work-time.other-work.categories.index';

    public const MANAGER_OTHER_WORK_CATEGORY_CREATE = 'manager.work-time.other-work.categories.create';

    public const MANAGER_OTHER_WORK_CATEGORY_STORE = 'manager.work-time.other-work.categories.store';

    public const MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE = 'manager.work-time.other-work.categories.deactivate';

    public const ACTIVITY_RECORD = 'time-tracking.activity.record';

    public const BREAK_START = 'users.work-time.break.start';

    public const BREAK_SHOW = 'users.work-time.break.show';

    public const BREAK_END = 'users.work-time.break.end';

    public const OTHER_WORK_CREATE = 'users.work-time.other-work.create';

    public const OTHER_WORK_START = 'users.work-time.other-work.start';

    public const OTHER_WORK_SHOW = 'users.work-time.other-work.show';

    public const OTHER_WORK_END = 'users.work-time.other-work.end';

    public const ADMIN_SUMMARY = 'admin.work-time.summary.index';

    public const ADMIN_OTHER_WORK = 'admin.work-time.other-work.index';

    public const ADMIN_WORK_SESSIONS = 'admin.work-time.work-sessions.index';

    public const ADMIN_BREAKS = 'admin.work-time.breaks.index';

    public const ADMIN_CORRECTIONS = 'admin.work-time.corrections.index';

    public const ADMIN_WORK_SESSION_SHOW = 'admin.work-time.work-sessions.show';

    public const ADMIN_BREAK_SHOW = 'admin.work-time.breaks.show';

    public const ADMIN_OTHER_WORK_SHOW = 'admin.work-time.other-work.show';

    public const ADMIN_CORRECTION_SHOW = 'admin.work-time.corrections.show';

    public const ADMIN_CLOSED_PERIOD_OVERRIDE = 'admin.time-tracking.closed-period-corrections.store';

    public const ADMIN_TERMINATE_SESSION = 'admin.work-time.work-sessions.terminate';

    public const ADMIN_BREAK_FORCE_CLOSE = 'admin.work-time.breaks.force-close';

    public const ADMIN_BREAK_CONVERT_EXCESS = 'admin.work-time.breaks.convert-excess';

    public const ADMIN_OTHER_WORK_FORCE_CLOSE = 'admin.work-time.other-work.force-close';

    public const ADMIN_OTHER_WORK_DECIDE = 'admin.work-time.other-work.decide';

    public const ADMIN_CORRECTION_DECIDE = 'admin.work-time.corrections.decide';

    public const ADMIN_MANUAL_ENTRY = 'admin.work-time.corrections.manual-entry';

    public const ADMIN_MANUAL_ENTRY_STORE = 'admin.work-time.corrections.manual-entry.store';

    public const ADMIN_OTHER_WORK_CATEGORY_STORE = 'admin.work-time.other-work.categories.store';

    public const ADMIN_OTHER_WORK_CATEGORY_INDEX = 'admin.work-time.other-work.categories.index';

    public const ADMIN_OTHER_WORK_CATEGORY_CREATE = 'admin.work-time.other-work.categories.create';

    public const ADMIN_OTHER_WORK_CATEGORY_DEACTIVATE = 'admin.work-time.other-work.categories.deactivate';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::MANAGER_PANEL, 'View the manager TimeTracking panel for the active team.'),
            new ModulePermissionDefinition(self::USER_REPORT, 'View own TimeTracking report for the active team.'),
            new ModulePermissionDefinition(self::USER_CORRECTION_REQUEST_STORE, 'Request a correction for own visible TimeTracking records.'),
            new ModulePermissionDefinition(self::MANAGER_REPORT, 'View TimeTracking report for users in the manager hierarchy scope.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_SUMMARY, 'View manager-scoped work-time summaries.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_OTHER_WORK, 'View manager-scoped work outside the computer records.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_WORK_SESSIONS, 'View manager-scoped work sessions.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_BREAKS, 'View manager-scoped breaks.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_CORRECTIONS, 'View manager-scoped correction requests and decisions.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_WORK_SESSION_SHOW, 'View manager-scoped work-session details.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_BREAK_SHOW, 'View manager-scoped break details.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_OTHER_WORK_SHOW, 'View manager-scoped work outside the computer details.'),
            new ModulePermissionDefinition(self::MANAGER_WORK_TIME_CORRECTION_SHOW, 'View manager-scoped correction details.'),
            new ModulePermissionDefinition(self::MANAGER_TERMINATE_SESSION, 'Terminate active manager-scoped work sessions.'),
            new ModulePermissionDefinition(self::MANAGER_BREAK_FORCE_CLOSE, 'Force-close active manager-scoped breaks.'),
            new ModulePermissionDefinition(self::MANAGER_BREAK_CONVERT_EXCESS, 'Convert excess manager-scoped break time through an audited correction.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_FORCE_CLOSE, 'Force-close active manager-scoped work outside the computer records.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_DECIDE, 'Approve or reject manager-scoped work outside the computer records.'),
            new ModulePermissionDefinition(self::MANAGER_CORRECTION_DECIDE, 'Decide manager-scoped correction requests within manager deadlines.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_CATEGORY_INDEX, 'View manager-scoped TimeTracking work outside the computer categories.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_CATEGORY_CREATE, 'View the manager form for creating team TimeTracking work outside the computer categories.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_CATEGORY_STORE, 'Create or update team TimeTracking work outside the computer categories as an eligible manager.'),
            new ModulePermissionDefinition(self::MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE, 'Deactivate team TimeTracking work outside the computer categories as an eligible manager.'),
            new ModulePermissionDefinition(self::ACTIVITY_RECORD, 'Record own browser activity and inactivity warning state.'),
            new ModulePermissionDefinition(self::BREAK_START, 'Start a TimeTracking break for the active work session.'),
            new ModulePermissionDefinition(self::BREAK_SHOW, 'View the active TimeTracking break lock screen.'),
            new ModulePermissionDefinition(self::BREAK_END, 'End the active TimeTracking break after password and MFA confirmation when required.'),
            new ModulePermissionDefinition(self::OTHER_WORK_CREATE, 'View the form for starting a TimeTracking work outside the computer record.'),
            new ModulePermissionDefinition(self::OTHER_WORK_START, 'Start a TimeTracking work outside the computer record for the active work session.'),
            new ModulePermissionDefinition(self::OTHER_WORK_SHOW, 'View the active TimeTracking work outside the computer lock screen.'),
            new ModulePermissionDefinition(self::OTHER_WORK_END, 'End the active TimeTracking work outside the computer record after password and MFA confirmation when required.'),
            new ModulePermissionDefinition(self::ADMIN_SUMMARY, 'View Admin work-time summaries for tracked users.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK, 'View Admin work outside the computer records for tracked users.'),
            new ModulePermissionDefinition(self::ADMIN_WORK_SESSIONS, 'View Admin technical work-session drill-downs for tracked users.'),
            new ModulePermissionDefinition(self::ADMIN_BREAKS, 'View Admin break records requiring operational review or correction.'),
            new ModulePermissionDefinition(self::ADMIN_CORRECTIONS, 'View Admin work-time correction requests and decisions.'),
            new ModulePermissionDefinition(self::ADMIN_WORK_SESSION_SHOW, 'View Admin technical details for a TimeTracking work session.'),
            new ModulePermissionDefinition(self::ADMIN_BREAK_SHOW, 'View Admin technical details for a TimeTracking break.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_SHOW, 'View Admin technical details for a TimeTracking work outside the computer record.'),
            new ModulePermissionDefinition(self::ADMIN_CORRECTION_SHOW, 'View Admin technical details for a TimeTracking correction request.'),
            new ModulePermissionDefinition(self::ADMIN_CLOSED_PERIOD_OVERRIDE, 'Create exceptional closed-period TimeTracking corrections when no eligible head manager exists.'),
            new ModulePermissionDefinition(self::ADMIN_TERMINATE_SESSION, 'Terminate active TimeTracking work sessions from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_BREAK_FORCE_CLOSE, 'Force-close active TimeTracking breaks from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_BREAK_CONVERT_EXCESS, 'Convert excess regular break time into counted work through an audited final correction.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_FORCE_CLOSE, 'Force-close active TimeTracking work outside the computer records from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_DECIDE, 'Approve or reject TimeTracking work outside the computer records from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_CORRECTION_DECIDE, 'Approve, reject, correct, or take over TimeTracking correction requests from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_MANUAL_ENTRY, 'View the form for creating exceptional manual TimeTracking entries from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_MANUAL_ENTRY_STORE, 'Create exceptional manual TimeTracking entries from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_CATEGORY_INDEX, 'View TimeTracking work outside the computer categories from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_CATEGORY_CREATE, 'View the form for creating TimeTracking work outside the computer categories from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_CATEGORY_STORE, 'Create or update TimeTracking Other work categories from the Admin work-time records area.'),
            new ModulePermissionDefinition(self::ADMIN_OTHER_WORK_CATEGORY_DEACTIVATE, 'Deactivate TimeTracking Other work categories from the Admin work-time records area.'),
        ];
    }
}
