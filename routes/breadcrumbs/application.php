<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

if (! function_exists('atlas_application_root')) {
    function atlas_application_root(Generator $breadcrumbs): void
    {
        $breadcrumbs->push(__('breadcrumbs.atlas'), route('dashboard'));
    }
}

if (! function_exists('atlas_user_panel_root')) {
    function atlas_user_panel_root(Generator $breadcrumbs): void
    {
        atlas_application_root($breadcrumbs);
        $breadcrumbs->push(__('breadcrumbs.user_panel'), route('users.profile'));
    }
}

if (! function_exists('atlas_manager_panel_root')) {
    function atlas_manager_panel_root(Generator $breadcrumbs): void
    {
        atlas_application_root($breadcrumbs);
        $breadcrumbs->push(__('breadcrumbs.manager_panel'), route('time-tracking.panels.manager'));
    }
}

if (! function_exists('atlas_breadcrumb_resource_action')) {
    function atlas_breadcrumb_resource_action(string $translationKey, mixed $identifier): string
    {
        $id = is_scalar($identifier) ? trim((string) $identifier) : '';
        $action = __($translationKey);

        return $id === ''
            ? $action
            : __('breadcrumbs.resource_action', ['action' => $action, 'id' => $id]);
    }
}

Breadcrumbs::for('dashboard', function (Generator $breadcrumbs): void {
    atlas_application_root($breadcrumbs);
});

Breadcrumbs::for('users.notifications.index', function (Generator $breadcrumbs): void {
    atlas_user_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.notifications'), route('users.notifications.index'));
});

Breadcrumbs::for('users.profile', function (Generator $breadcrumbs): void {
    atlas_user_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.user_dashboard'), route('users.profile'));
});

Breadcrumbs::for('time-tracking.panels.manager', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.manager_dashboard'), route('time-tracking.panels.manager'));
});

Breadcrumbs::for('users.work-time', function (Generator $breadcrumbs): void {
    atlas_user_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.time_tracking_user_report'), route('users.work-time'));
});

Breadcrumbs::for('users.work-time.other-work.create', function (Generator $breadcrumbs): void {
    atlas_user_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.time_tracking_user_report'), route('users.work-time'));
    $breadcrumbs->push(__('breadcrumbs.time_tracking_other_work_start'), route('users.work-time.other-work.create'));
});

Breadcrumbs::for('time-tracking.reports.manager', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.time_tracking_manager_report'), route('time-tracking.reports.manager'));
});

Breadcrumbs::for('manager.work-time.summary.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_daily'), route('manager.work-time.summary.index'));
});

Breadcrumbs::for('manager.work-time.other-work.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('manager.work-time.other-work.index'));
});

Breadcrumbs::for('manager.work-time.other-work.categories.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('manager.work-time.other-work.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories'), route('manager.work-time.other-work.categories.index'));
});

Breadcrumbs::for('manager.work-time.other-work.categories.create', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('manager.work-time.other-work.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories'), route('manager.work-time.other-work.categories.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories_create'));
});

Breadcrumbs::for('manager.work-time.work-sessions.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_sessions'), route('manager.work-time.work-sessions.index'));
});

Breadcrumbs::for('manager.work-time.work-sessions.show', function (Generator $breadcrumbs, string $session): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_sessions'), route('manager.work-time.work-sessions.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $session));
});

Breadcrumbs::for('manager.work-time.breaks.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_breaks'), route('manager.work-time.breaks.index'));
});

Breadcrumbs::for('manager.work-time.breaks.show', function (Generator $breadcrumbs, string $break): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_breaks'), route('manager.work-time.breaks.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $break));
});

Breadcrumbs::for('manager.work-time.corrections.index', function (Generator $breadcrumbs): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_corrections'), route('manager.work-time.corrections.index'));
});

Breadcrumbs::for('manager.work-time.other-work.show', function (Generator $breadcrumbs, string $otherWork): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('manager.work-time.other-work.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $otherWork));
});

Breadcrumbs::for('manager.work-time.corrections.show', function (Generator $breadcrumbs, string $correction): void {
    atlas_manager_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_corrections'), route('manager.work-time.corrections.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $correction));
});

Breadcrumbs::for('team.select', function (Generator $breadcrumbs): void {
    atlas_application_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.team_select'), route('team.select'));
});
